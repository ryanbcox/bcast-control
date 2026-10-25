#!/usr/bin/env python3
"""
Always-on audio-level meter for the web panel. Independent of
ndi-relay.service / RELAY_MODE -- opens its own NDI receive connection
(NDI supports multiple simultaneous receivers of the same source) so the
meter keeps working across scene switches and relay restarts, and reflects
the camera's real mic input even in Blank/Sacrament mode.

Taps GstLevel's bus messages directly via the GObject-introspection
bindings (rather than parsing gst-launch-1.0 -m text output) and writes the
current reading to a JSON file, atomically, for the web panel to poll.
"""
import json
import os
import time

import gi

gi.require_version("Gst", "1.0")
from gi.repository import GLib, Gst

OUTPUT_PATH = "/run/bcast-control/audio-level.json"
INTERVAL_NS = 200_000_000  # level element's message interval: 200ms


def write_level(rms, peak, active):
    tmp_path = OUTPUT_PATH + ".tmp"
    payload = {
        "rms": rms,
        "peak": peak,
        "active": active,
        "updated": time.time(),
    }
    with open(tmp_path, "w") as f:
        json.dump(payload, f)
    os.replace(tmp_path, OUTPUT_PATH)


def on_message(bus, message, loop):
    if message.type == Gst.MessageType.ELEMENT:
        structure = message.get_structure()
        if structure and structure.get_name() == "level":
            rms = list(structure.get_value("rms"))
            peak = list(structure.get_value("peak"))
            write_level(rms, peak, True)
    elif message.type in (Gst.MessageType.ERROR, Gst.MessageType.EOS):
        if message.type == Gst.MessageType.ERROR:
            err, debug = message.parse_error()
            print(f"GStreamer error: {err} ({debug})", flush=True)
        else:
            print("GStreamer EOS", flush=True)
        loop.quit()
    return True


def main():
    ndi_url_address = os.environ["NDI_URL_ADDRESS"]

    Gst.init(None)

    pipeline_desc = (
        f'ndisrc url-address="{ndi_url_address}" ! ndisrcdemux name=demux '
        f"demux.audio ! queue ! audioconvert ! audioresample ! "
        f"level interval={INTERVAL_NS} post-messages=true ! fakesink sync=false "
        f"demux.video ! queue ! fakesink sync=false"
    )
    pipeline = Gst.parse_launch(pipeline_desc)

    loop = GLib.MainLoop()
    bus = pipeline.get_bus()
    bus.add_signal_watch()
    bus.connect("message", on_message, loop)

    # If NDI goes away, no level messages arrive -- write-staleness (the
    # "updated" timestamp) is how the web panel notices "no signal", so no
    # separate watchdog is needed here.
    write_level([], [], False)

    pipeline.set_state(Gst.State.PLAYING)
    try:
        loop.run()
    finally:
        pipeline.set_state(Gst.State.NULL)


if __name__ == "__main__":
    main()

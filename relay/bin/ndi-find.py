#!/usr/bin/env python3
"""List NDI sources visible on the network right now.
Usage: GST_PLUGIN_PATH=/opt/ndi-relay/gst-plugins ./ndi-find.py
"""
import gi
gi.require_version('Gst', '1.0')
from gi.repository import Gst
import time

Gst.init(None)
mon = Gst.DeviceMonitor.new()
mon.add_filter("Source/Network:application/x-ndi", None)
mon.start()
time.sleep(4)
devices = mon.get_devices()
if not devices:
    print("No NDI sources found (checked for 4s).")
else:
    for d in devices:
        print(d.get_display_name())
mon.stop()

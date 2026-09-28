#!/bin/bash
# Switches the NDI relay's active "scene" by writing RELAY_MODE into
# relay-mode.env and, ONLY IF the relay is already running, restarting
# ndi-relay.service to pick it up.
#
# IMPORTANT: only restart if already active. `systemctl restart` on a
# stopped unit actually STARTS it -- which meant clicking Blank/Sacrament/
# Normal while stopped would silently start streaming as a side effect.
# Scene buttons must never affect whether streaming is active at all;
# only start.php/stop.php do that. (Bug found and fixed 2026-09-20.)
#
# KNOWN LIMITATION: when the relay IS running, changing scene causes a
# full process restart of the gst-launch-1.0 pipeline, which means a brief
# RTMP reconnect/drop on YouTube's end every time the scene changes (a few
# seconds of "reconnecting"/frozen frame). This is a deliberate simplicity
# tradeoff, not a bug -- see /root/claude/docs/NDI_RELAY_ALTERNATIVE.md for
# details, and revisit this script if a seamless (no-restart) scene switch
# is ever required.
set -eu

MODE="${1:-}"

case "$MODE" in
  Broadcast|Sacrament|Blank)
    ;;
  *)
    echo "Usage: $0 <Broadcast|Sacrament|Blank>" >&2
    exit 1
    ;;
esac

echo "RELAY_MODE=${MODE}" > /opt/ndi-relay/relay-mode.env
chown broadcast:broadcast /opt/ndi-relay/relay-mode.env
chmod 644 /opt/ndi-relay/relay-mode.env

if systemctl is-active --quiet ndi-relay.service; then
  systemctl restart ndi-relay.service
fi

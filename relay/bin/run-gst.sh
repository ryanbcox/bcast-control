#!/bin/bash
# Run one relay pipeline and tap gst-launch's level messages without making
# another NDI receiver. The audio-level helper consumes stdin and forwards
# non-level diagnostics to journald.
set -uo pipefail

gst-launch-1.0 -m -e "$@" 2>&1 | /opt/ndi-relay/bin/audio-level-monitor.py

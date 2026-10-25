#!/bin/bash
# Keeps ndi-relay-preview.service (the same relay pipeline as a real
# broadcast, just pointed at a fakesink instead of YouTube -- see
# run-relay.sh's RELAY_DESTINATION) running only while someone's actually
# looking at the web panel's preview image, and only when the real
# broadcast (ndi-relay.service) isn't already covering it via its own tee.
#
# This itself holds no NDI connection and does no GStreamer work -- it just
# starts/stops the other unit based on two signals: the preview-lastpoll
# marker file (touched by preview.php on every browser fetch) and whether
# the real relay is active.
set -eu

LASTPOLL_FILE=/run/bcast-control-web/preview-lastpoll
IDLE_TIMEOUT_SECONDS=5
POLL_INTERVAL_SECONDS=2

while true; do
  now=$(date +%s)

  if [ -f "$LASTPOLL_FILE" ]; then
    last=$(stat -c %Y "$LASTPOLL_FILE" 2>/dev/null || echo 0)
  else
    last=0
  fi
  age=$(( now - last ))

  real_relay_active=false
  if systemctl is-active --quiet ndi-relay.service; then
    real_relay_active=true
  fi

  preview_active=false
  if systemctl is-active --quiet ndi-relay-preview.service; then
    preview_active=true
  fi

  if [ "$real_relay_active" = true ]; then
    # The real broadcast's own tee already writes cam.jpg -- a second NDI
    # connection here would be redundant.
    if [ "$preview_active" = true ]; then
      systemctl stop ndi-relay-preview.service
    fi
  elif [ "$age" -le "$IDLE_TIMEOUT_SECONDS" ]; then
    if [ "$preview_active" = false ]; then
      systemctl start ndi-relay-preview.service
    fi
  else
    if [ "$preview_active" = true ]; then
      systemctl stop ndi-relay-preview.service
    fi
  fi

  sleep "$POLL_INTERVAL_SECONDS"
done

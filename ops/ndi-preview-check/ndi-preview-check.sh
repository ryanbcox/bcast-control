#!/bin/bash
# Runs every 2 minutes via cron (see /etc/cron.d/ndi-preview-check-cron).
#
# ndi-preview.service has a known startup race: it can settle into
# "PLAYING" and stay active(running) indefinitely while never actually
# writing a single frame to cam.jpg (seen after reboots on 2026-09-20 and
# 2026-09-21) -- systemd's Restart=on-failure does not catch this because
# the process never actually exits/fails, it just silently produces
# nothing. This checks the age of the preview file instead and force-
# restarts the service if it's stale or missing.
set -u

FILE=/run/bcast-control/cam.jpg
MAX_AGE=60

if [ -f "$FILE" ]; then
  AGE=$(( $(date +%s) - $(stat -c %Y "$FILE") ))
else
  AGE=999999
fi

if [ "$AGE" -gt "$MAX_AGE" ]; then
  logger -t ndi-preview-check "cam.jpg stale or missing (age=${AGE}s) -- restarting ndi-preview.service"
  systemctl restart ndi-preview.service
fi

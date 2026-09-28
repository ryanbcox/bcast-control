#!/bin/bash
# Runs every minute via cron (see /etc/cron.d/broadcast-watchdog-cron).
# Pats the hardware watchdog directly, but ONLY if both health checks pass.
# This is deliberately stricter than systemd's own default notion of
# "alive" -- ps and ls actually succeeding means the process table and a
# real filesystem path are both genuinely responsive right now.
#
# Patting is a plain write to /dev/watchdog (nowayout=1 means this short-
# lived open/write/close cycle never disarms it -- only a sustained failure
# to write here for 5 minutes lets the hardware actually reboot).
set -u

FAILED=""

if ! ps -ef >/dev/null 2>&1; then
  FAILED="${FAILED}ps -ef "
fi

if ! ls /tmp >/dev/null 2>&1; then
  FAILED="${FAILED}ls /tmp "
fi

if [ -z "$FAILED" ]; then
  printf '\0' > /dev/watchdog 2>/dev/null
else
  wall "watchdog-check: health check FAILED (${FAILED}) -- NOT patting hardware watchdog"
fi

#!/bin/bash
# Reboots the machine if the default gateway has been unreachable for
# longer than $THRESHOLD_SECS. Run periodically by gateway-watchdog.timer.
set -u

STATE_FILE=/run/gateway-watchdog.first-failure
THRESHOLD_SECS=$((30 * 60))

GATEWAY=$(ip route show default | awk '/^default/ {print $3; exit}')

if [ -z "$GATEWAY" ]; then
	logger -t gateway-watchdog "No default route found; treating as unreachable"
	REACHABLE=0
elif ping -c1 -W5 "$GATEWAY" >/dev/null 2>&1; then
	REACHABLE=1
else
	REACHABLE=0
fi

if [ "$REACHABLE" = 1 ]; then
	rm -f "$STATE_FILE"
	exit 0
fi

NOW=$(date +%s)

if [ ! -f "$STATE_FILE" ]; then
	echo "$NOW" >"$STATE_FILE"
	logger -t gateway-watchdog "Gateway ${GATEWAY:-unknown} unreachable; starting ${THRESHOLD_SECS}s countdown"
	exit 0
fi

FIRST_FAILURE=$(cat "$STATE_FILE" 2>/dev/null || echo "$NOW")
ELAPSED=$((NOW - FIRST_FAILURE))

logger -t gateway-watchdog "Gateway ${GATEWAY:-unknown} still unreachable (${ELAPSED}s of ${THRESHOLD_SECS}s)"

if [ "$ELAPSED" -ge "$THRESHOLD_SECS" ]; then
	logger -t gateway-watchdog "Gateway unreachable for ${ELAPSED}s; rebooting"
	rm -f "$STATE_FILE"
	/sbin/reboot
fi

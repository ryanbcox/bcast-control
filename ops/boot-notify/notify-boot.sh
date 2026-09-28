#!/bin/bash
set -eu
BODY="$(uptime)
$(last | head -5)
Called by: $0"
/usr/local/sbin/notify.sh "Broadcast Computer booted up" "$BODY"

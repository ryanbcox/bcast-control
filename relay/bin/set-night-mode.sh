#!/bin/bash
# Night Test Mode: changes the camera's own "bright" setting (0-14 range,
# via the camera's documented HTTP CGI) for low-light testing. Pure
# hardware/camera-side change -- no GStreamer pipeline restart needed,
# since the brightened signal just flows through as part of the camera's
# normal live video. (A software videobalance/gamma boost was tried first
# and dropped -- it washed the image out gray.)
#
# Every change is logged with a from/to value and a timestamp to
# camera-image-changelog.log.
#
# Derives the camera's IP from NDI_URL_ADDRESS in relay.env (same camera,
# same network) rather than hardcoding it a second time here. Invoked
# directly via sudo from the web panel (not through systemd), so it
# sources relay.env itself instead of relying on EnvironmentFile=.
set -eu

MODE="${1:-}"

case "$MODE" in
  on|off)
    ;;
  *)
    echo "Usage: $0 <on|off>" >&2
    exit 1
    ;;
esac

set -a
. /opt/ndi-relay/relay.env
set +a
: "${NDI_URL_ADDRESS:?Set NDI_URL_ADDRESS in /opt/ndi-relay/relay.env}"
CAMERA_HOST="${NDI_URL_ADDRESS%%:*}"

CHANGELOG=/opt/ndi-relay/camera-info/camera-image-changelog.log
CAMERA_BASE="http://${CAMERA_HOST}/cgi-bin/param.cgi"
TS="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

if [ "$MODE" = "on" ]; then
  TARGET_BRIGHT=14
else
  TARGET_BRIGHT=7
fi

BEFORE="$(curl -s -m 3 "${CAMERA_BASE}?get_image_conf" | grep '^bright=' || echo 'bright="unknown"')"
curl -s -m 3 "${CAMERA_BASE}?post_image_value&bright&${TARGET_BRIGHT}" > /tmp/night-mode-camera-response.$$ 2>&1 || true
RESPONSE="$(cat /tmp/night-mode-camera-response.$$ 2>/dev/null || echo 'no response')"
rm -f /tmp/night-mode-camera-response.$$
sleep 0.3
AFTER="$(curl -s -m 3 "${CAMERA_BASE}?get_image_conf" | grep '^bright=' || echo 'bright="unknown"')"

echo "[$TS] set-night-mode.sh $MODE: camera bright before=$BEFORE after=$AFTER (target=$TARGET_BRIGHT) url=${CAMERA_BASE}?post_image_value\&bright\&${TARGET_BRIGHT} response=$RESPONSE" >> "$CHANGELOG"

# Recorded purely for status.php's benefit -- nothing in the GStreamer
# pipelines reads this anymore, the camera change is live on its own.
echo "NIGHT_MODE=${MODE}" > /opt/ndi-relay/night-mode.env
chown broadcast:broadcast /opt/ndi-relay/night-mode.env
chmod 644 /opt/ndi-relay/night-mode.env

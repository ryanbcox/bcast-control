#!/bin/bash
# GStreamer NDI -> RTMP(YouTube) relay. No OBS, no GUI, no VNC.
# Reads config from /opt/ndi-relay/relay.env (loaded by systemd as
# EnvironmentFile= for ndi-relay.service), and RELAY_MODE from
# /opt/ndi-relay/relay-mode.env (optional EnvironmentFile=, defaults to
# Broadcast if absent/unset -- see set-scene.sh for how RELAY_MODE changes).
set -eu

: "${NDI_URL_ADDRESS:?Set NDI_URL_ADDRESS in relay.env}"
: "${YOUTUBE_RTMP_URL:?Set YOUTUBE_RTMP_URL in relay.env}"
: "${YOUTUBE_STREAM_KEY:?Set YOUTUBE_STREAM_KEY in relay.env}"
: "${VIDEO_BITRATE_KBPS:=4500}"
: "${AUDIO_BITRATE_BPS:=128000}"
: "${RELAY_MODE:=Broadcast}"

export GST_PLUGIN_PATH=/opt/ndi-relay/gst-plugins

DEST="${YOUTUBE_RTMP_URL}/${YOUTUBE_STREAM_KEY} live=1"

case "$RELAY_MODE" in
  Broadcast)
    # Connects by direct IP:port (url-address) rather than by discovered
    # name (ndi-name) -- this network's Wi-Fi (Meraki-managed) filters the
    # multicast traffic NDI's normal discovery relies on, confirmed by
    # testing on 2026-09-19. url-address bypasses discovery entirely and is
    # what actually works here.
    exec gst-launch-1.0 -e \
      ndisrc url-address="${NDI_URL_ADDRESS}" ! ndisrcdemux name=demux \
      demux.video ! queue ! videoconvert ! videorate ! video/x-raw,framerate=30/1 ! \
        x264enc bitrate="${VIDEO_BITRATE_KBPS}" tune=zerolatency key-int-max=60 speed-preset=veryfast ! \
        h264parse ! flvmux name=mux streamable=true ! \
        rtmpsink location="${DEST}" \
      demux.audio ! queue ! audioconvert ! audioresample ! \
        voaacenc bitrate="${AUDIO_BITRATE_BPS}" ! aacparse ! mux.
    ;;
  Blank|Sacrament)
    if [ "$RELAY_MODE" = "Blank" ]; then
      IMAGE=/opt/ndi-relay/images/blank.png
    else
      IMAGE=/opt/ndi-relay/images/sacrament.png
    fi
    : "${IMAGE:?}"
    [ -f "$IMAGE" ] || { echo "Missing placeholder image: $IMAGE" >&2; exit 1; }
    exec gst-launch-1.0 -e \
      filesrc location="${IMAGE}" ! decodebin ! imagefreeze ! videoconvert ! videoscale ! \
        video/x-raw,width=1280,height=720,framerate=30/1 ! \
        x264enc bitrate="${VIDEO_BITRATE_KBPS}" tune=zerolatency key-int-max=60 speed-preset=veryfast ! \
        h264parse ! flvmux name=mux streamable=true ! \
        rtmpsink location="${DEST}" \
      audiotestsrc wave=silence is-live=true ! audioconvert ! audioresample ! \
        voaacenc bitrate="${AUDIO_BITRATE_BPS}" ! aacparse ! mux.
    ;;
  *)
    echo "Unknown RELAY_MODE: ${RELAY_MODE}" >&2
    exit 1
    ;;
esac

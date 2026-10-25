#!/bin/bash
# GStreamer NDI -> RTMP(YouTube) relay. No OBS, no GUI, no VNC.
# Reads config from /opt/ndi-relay/relay.env (loaded by systemd as
# EnvironmentFile= for ndi-relay.service), and RELAY_MODE from
# /opt/ndi-relay/relay-mode.env (optional EnvironmentFile=, defaults to
# Broadcast if absent/unset -- see set-scene.sh for how RELAY_MODE changes).
set -eu

: "${NDI_URL_ADDRESS:?Set NDI_URL_ADDRESS in relay.env}"
: "${VIDEO_BITRATE_KBPS:=4500}"
: "${AUDIO_BITRATE_BPS:=128000}"
: "${RELAY_MODE:=Broadcast}"
# "youtube" (default) sends the Broadcast-mode encode to the real YouTube
# ingest, same as always. "preview" is for the on-demand preview-only
# instance (ndi-relay-preview.service) -- identical pipeline, encode and
# all, just with the final sink swapped for a fakesink instead of an actual
# RTMP connection, so no YouTube credentials are touched/required and
# nothing is actually published. See NDI_RELAY_ALTERNATIVE.md.
: "${RELAY_DESTINATION:=youtube}"

export GST_PLUGIN_PATH=/opt/ndi-relay/gst-plugins

case "$RELAY_MODE" in
  Broadcast)
    # Connects by direct IP:port (url-address) rather than by discovered
    # name (ndi-name) -- this network's Wi-Fi (Meraki-managed) filters the
    # multicast traffic NDI's normal discovery relies on, confirmed by
    # testing on 2026-09-19. url-address bypasses discovery entirely and is
    # what actually works here.
    #
    # The video is tee'd: one branch goes to the real encode (to YouTube or
    # to a fakesink, depending on RELAY_DESTINATION below); the other is a
    # cheap low-framerate JPEG snapshot written for the web panel's preview
    # image, so a running instance of this exact pipeline -- real broadcast
    # or preview-only -- is always what's serving that image.
    if [ "$RELAY_DESTINATION" = "youtube" ]; then
      : "${YOUTUBE_RTMP_URL:?Set YOUTUBE_RTMP_URL in relay.env}"
      : "${YOUTUBE_STREAM_KEY:?Set YOUTUBE_STREAM_KEY in relay.env}"
      DEST="${YOUTUBE_RTMP_URL}/${YOUTUBE_STREAM_KEY} live=1"
      exec /opt/ndi-relay/bin/run-gst.sh \
        ndisrc url-address="${NDI_URL_ADDRESS}" ! ndisrcdemux name=demux \
        demux.video ! queue ! tee name=vtee \
        vtee. ! queue ! videoconvert ! videorate ! video/x-raw,framerate=30/1 ! \
          x264enc bitrate="${VIDEO_BITRATE_KBPS}" tune=zerolatency key-int-max=60 speed-preset=veryfast ! \
          h264parse ! flvmux name=mux streamable=true ! \
          rtmpsink location="${DEST}" \
        vtee. ! queue ! videorate ! video/x-raw,framerate=1/3 ! videoconvert ! jpegenc ! \
          multifilesink location=/run/bcast-control/cam.jpg \
        demux.audio ! queue ! audioconvert ! audioresample ! \
          level interval=200000000 post-messages=true ! \
          voaacenc bitrate="${AUDIO_BITRATE_BPS}" ! aacparse ! mux.
    else
      exec /opt/ndi-relay/bin/run-gst.sh \
        ndisrc url-address="${NDI_URL_ADDRESS}" ! ndisrcdemux name=demux \
        demux.video ! queue ! tee name=vtee \
        vtee. ! queue ! videoconvert ! videorate ! video/x-raw,framerate=30/1 ! \
          x264enc bitrate="${VIDEO_BITRATE_KBPS}" tune=zerolatency key-int-max=60 speed-preset=veryfast ! \
          h264parse ! flvmux name=mux streamable=true ! \
          fakesink sync=false \
        vtee. ! queue ! videorate ! video/x-raw,framerate=1/3 ! videoconvert ! jpegenc ! \
          multifilesink location=/run/bcast-control/cam.jpg \
        demux.audio ! queue ! audioconvert ! audioresample ! \
          level interval=200000000 post-messages=true ! \
          voaacenc bitrate="${AUDIO_BITRATE_BPS}" ! aacparse ! mux.
    fi
    ;;
  Blank|Sacrament)
    # Preview-only instances always force RELAY_MODE=Broadcast (see
    # ndi-relay-preview.service), so these scenes only ever run as the real
    # broadcast -- always require real YouTube credentials, same as before.
    : "${YOUTUBE_RTMP_URL:?Set YOUTUBE_RTMP_URL in relay.env}"
    : "${YOUTUBE_STREAM_KEY:?Set YOUTUBE_STREAM_KEY in relay.env}"
    DEST="${YOUTUBE_RTMP_URL}/${YOUTUBE_STREAM_KEY} live=1"
    if [ "$RELAY_MODE" = "Blank" ]; then
      IMAGE=/opt/ndi-relay/images/blank.png
    else
      IMAGE=/opt/ndi-relay/images/sacrament.png
    fi
    : "${IMAGE:?}"
    [ -f "$IMAGE" ] || { echo "Missing placeholder image: $IMAGE" >&2; exit 1; }
    exec /opt/ndi-relay/bin/run-gst.sh \
      filesrc location="${IMAGE}" ! decodebin ! imagefreeze ! videoconvert ! videoscale ! \
        video/x-raw,width=1280,height=720,framerate=30/1 ! \
        x264enc bitrate="${VIDEO_BITRATE_KBPS}" tune=zerolatency key-int-max=60 speed-preset=veryfast ! \
        h264parse ! flvmux name=mux streamable=true ! \
        rtmpsink location="${DEST}" \
      audiotestsrc wave=silence is-live=true ! audioconvert ! audioresample ! \
        level interval=200000000 post-messages=true ! \
        voaacenc bitrate="${AUDIO_BITRATE_BPS}" ! aacparse ! mux.
    ;;
  *)
    echo "Unknown RELAY_MODE: ${RELAY_MODE}" >&2
    exit 1
    ;;
esac

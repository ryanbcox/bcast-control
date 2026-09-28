#!/bin/bash
set -eu
: "${NDI_URL_ADDRESS:?Set NDI_URL_ADDRESS in relay.env}"
export GST_PLUGIN_PATH=/opt/ndi-relay/gst-plugins

exec gst-launch-1.0 -e \
  ndisrc url-address="${NDI_URL_ADDRESS}" ! ndisrcdemux name=demux \
  demux.video ! queue ! videorate ! video/x-raw,framerate=1/3 ! \
  videoconvert ! jpegenc ! \
  multifilesink location=/run/bcast-control/cam.jpg \
  demux.audio ! queue ! fakesink

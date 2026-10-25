<?php

// Touches a marker file every time a browser actually fetches the preview
// image, so the on-demand watchdog (ndi-watchdog.service) knows whether to
// keep a preview-only GStreamer pipeline running. Separate directory from
// /run/bcast-control because this one needs to be www-data-writable.
$lastpoll_file = '/run/bcast-control-web/preview-lastpoll';
touch($lastpoll_file);

$cam_file = '/run/bcast-control/cam.jpg';

if (is_readable($cam_file)) {
   header('Content-Type: image/jpeg');
   header('Cache-Control: no-store');
   readfile($cam_file);
} else {
   http_response_code(503);
}

?>

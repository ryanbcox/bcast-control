<?php

// Copy this file to vars.php and fill in real values for your install.
// vars.php is gitignored -- it holds this deployment's real camera IP and
// the sudo command strings used to control the GStreamer relay (see
// relay/README.md for that backend's own setup).

$ndi_relay_bin = "/opt/ndi-relay/bin/";
$set_scene = "sudo " . $ndi_relay_bin . "set-scene.sh";
$start_stream = "sudo /usr/bin/systemctl start ndi-relay.service";
$stop_stream = "sudo /usr/bin/systemctl stop ndi-relay.service";
$set_night_mode = "sudo " . $ndi_relay_bin . "set-night-mode.sh";
$relay_mode_file = "/opt/ndi-relay/relay-mode.env";
$night_mode_file = "/opt/ndi-relay/night-mode.env";
$relay_env_file = "/opt/ndi-relay/relay.env";

// Lowest/highest camera preset numbers exposed as buttons in the UI
// (config.js's "cameras" array should only use positions in this range).
$min_camera_pos = 61;
$max_camera_pos = 71;

// Camera's PTZ control CGI base URL -- update the IP for your camera.
$cam_base_url = "http://192.168.1.100/cgi-bin/ptzctrl.cgi?ptzcmd&";
$cam_pos_url = $cam_base_url . "poscall&";

// Manual PTZ pad speed limits (camera-specific -- check your camera's CGI
// docs for its supported speed range).
$min_ptz_speed = 1;
$max_ptz_speed = 24;
$min_zoom_speed = 1;
$max_zoom_speed = 8;

?>

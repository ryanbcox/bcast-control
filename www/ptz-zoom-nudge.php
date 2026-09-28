<?php

require_once 'vars.php';

// Same idea as ptz-nudge.php but for zoom: 60ms per speed unit
// (60ms at speed 1, 480ms at speed 8).
$valid_dirs = ['in', 'out'];

$dir = $_GET['dir'] ?? '';
if (!in_array($dir, $valid_dirs, true)) {
   http_response_code(400);
   die("invalid dir");
}

$speed = isset($_GET['speed']) ? (int)$_GET['speed'] : $min_zoom_speed;
if ($speed < $min_zoom_speed) {
   $speed = $min_zoom_speed;
}
if ($speed > $max_zoom_speed) {
   $speed = $max_zoom_speed;
}

$duration_us = $speed * 60000;

file_get_contents($cam_base_url . 'zoom' . $dir . '&' . $speed);
usleep($duration_us);
file_get_contents($cam_base_url . 'zoomstop');

print(json_encode(['Result' => '200']));

?>

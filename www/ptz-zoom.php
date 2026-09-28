<?php

require_once 'vars.php';

$valid_dirs = ['in', 'out', 'stop'];

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

if ($dir === 'stop') {
   $url = $cam_base_url . 'zoomstop';
} else {
   $url = $cam_base_url . 'zoom' . $dir . '&' . $speed;
}

file_get_contents($url);

print(json_encode(['Result' => '200']));

?>

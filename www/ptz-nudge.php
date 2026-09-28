<?php

require_once 'vars.php';

// Direction move + wait + stop, all in one request, so a "tap" always moves
// a deterministic amount regardless of client-side click/network timing.
// Duration scales with speed: 25ms per speed unit (25ms at speed 1, 600ms
// at speed 24) -- low number, short distance; big number, long distance.
$valid_dirs = ['up', 'down', 'left', 'right', 'leftup', 'leftdown', 'rightup', 'rightdown'];

$dir = $_GET['dir'] ?? '';
if (!in_array($dir, $valid_dirs, true)) {
   http_response_code(400);
   die("invalid dir");
}

$speed = isset($_GET['speed']) ? (int)$_GET['speed'] : $min_ptz_speed;
if ($speed < $min_ptz_speed) {
   $speed = $min_ptz_speed;
}
if ($speed > $max_ptz_speed) {
   $speed = $max_ptz_speed;
}

$duration_us = $speed * 25000;

file_get_contents($cam_base_url . $dir . '&' . $speed);
usleep($duration_us);
file_get_contents($cam_base_url . 'ptzstop&' . $speed . '&' . $speed);

print(json_encode(['Result' => '200']));

?>

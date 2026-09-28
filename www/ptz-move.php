<?php

require_once 'vars.php';

$valid_dirs = ['up', 'down', 'left', 'right', 'leftup', 'leftdown', 'rightup', 'rightdown', 'home', 'stop'];

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

if ($dir === 'home') {
   $url = $cam_base_url . 'home';
} elseif ($dir === 'stop') {
   $url = $cam_base_url . 'ptzstop&' . $speed . '&' . $speed;
} else {
   $url = $cam_base_url . $dir . '&' . $speed;
}

file_get_contents($url);

print(json_encode(['Result' => '200']));

?>

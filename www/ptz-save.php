<?php
require_once 'vars.php';

function valid_preset_number($p) {
   if (!is_numeric($p)) return false;
   $p = (int)$p;
   return ($p >= 0 && $p <= 89) || ($p >= 100 && $p <= 254);
}

$pos = $_POST['pos'] ?? null;

if (!valid_preset_number($pos)) {
   http_response_code(400);
   print(json_encode(['Result' => '400', 'error' => 'Invalid preset number.']));
   exit;
}

file_get_contents($cam_base_url . "posset&" . (int)$pos);

http_response_code(200);
print(json_encode(['Result' => '200']));

?>

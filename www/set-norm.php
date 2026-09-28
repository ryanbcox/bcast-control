<?php

require_once 'vars.php';

exec($set_scene . " Broadcast", $output, $code);

http_response_code($code === 0 ? 200 : 500);
print(json_encode(['Result' => $code === 0 ? '200' : '500']));

?>

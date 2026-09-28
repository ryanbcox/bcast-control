<?php

require_once 'vars.php';

$enable = isset($_GET['enable']) && $_GET['enable'] === '1';

exec($set_night_mode . ' ' . ($enable ? 'on' : 'off'), $output, $code);

http_response_code($code === 0 ? 200 : 500);
print(json_encode(['Result' => $code === 0 ? '200' : '500']));

?>

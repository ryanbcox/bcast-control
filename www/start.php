<?php

require_once 'vars.php';

// Ensure scene is Broadcast before/while starting so a fresh stream never
// silently comes up still in Blank/Sacrament mode from a previous session.
exec($set_scene . " Broadcast");
exec($start_stream, $output, $code);

http_response_code($code === 0 ? 200 : 500);
print(json_encode(['Result' => $code === 0 ? '200' : '500']));

?>

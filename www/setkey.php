<?php
http_response_code(410);
print(json_encode(['error' => 'Stream key selection is not supported by the relay path.']));
?>

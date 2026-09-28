<?php
// Requires a "day" POST parameter matching today's day of the month
// (server's LOCAL time, not UTC -- PHP's own default timezone on this box
// is UTC, so it must be set explicitly here, not left to the default).
// Leading zeros and surrounding whitespace are ignored via (int) casting
// a trimmed string, which reads leading digits without any octal
// interpretation (unlike intval() with a base of 0).
date_default_timezone_set('America/Denver');

$submitted = trim((string)($_POST['day'] ?? ''));
$submittedDay = (int)$submitted;
$today = (int)date('j');

if ($submitted === '' || $submittedDay !== $today) {
   http_response_code(403);
   print(json_encode(['Result' => '403', 'error' => 'Day of month did not match. Reboot cancelled.']));
   exit;
}

exec('sudo /usr/sbin/reboot', $output, $code);
http_response_code($code === 0 ? 200 : 500);
print(json_encode(['Result' => $code === 0 ? '200' : '500']));
?>

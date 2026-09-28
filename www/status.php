<?php

require_once 'vars.php';

$active = trim(shell_exec('systemctl is-active ndi-relay.service 2>/dev/null'));
$streamingStatus = ($active === 'active');

$currentScene = 'Broadcast';
if (is_readable($relay_mode_file)) {
   foreach (file($relay_mode_file) as $line) {
      if (preg_match('/^RELAY_MODE=(.*)$/', trim($line), $m)) {
         $val = trim($m[1]);
         if (in_array($val, ['Broadcast', 'Sacrament', 'Blank'], true)) {
            $currentScene = $val;
         }
      }
   }
}

$nightMode = false;
if (is_readable($night_mode_file)) {
   foreach (file($night_mode_file) as $line) {
      if (preg_match('/^NIGHT_MODE=(.*)$/', trim($line), $m)) {
         $nightMode = trim($m[1]) === 'on';
      }
   }
}

// No per-request secret is exposed here on purpose -- this endpoint has no
// auth, and the relay has exactly one configured destination, so there is
// no meaningful "which key" choice to report. bcast.js only uses this to
// build a display label.
$streamKey = 'default';

print(json_encode([
   'currentScene' => $currentScene,
   'nightMode' => $nightMode,
   'streamingStatus' => $streamingStatus,
   'streamKey' => $streamKey,
]));

?>

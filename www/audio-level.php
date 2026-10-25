<?php

$level_file = '/run/bcast-control/audio-level.json';
$stale_after_seconds = 2.0;

$rms = [];
$peak = [];
$active = false;
$stale = true;

if (is_readable($level_file)) {
   $data = json_decode(file_get_contents($level_file), true);
   if (is_array($data)) {
      $rms = $data['rms'] ?? [];
      $peak = $data['peak'] ?? [];
      $active = $data['active'] ?? false;
      $updated = $data['updated'] ?? 0;
      $stale = (microtime(true) - $updated) > $stale_after_seconds;
   }
}

print(json_encode([
   'rms' => $rms,
   'peak' => $peak,
   'active' => $active && !$stale,
   'stale' => $stale,
]));

?>

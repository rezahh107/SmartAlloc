<?php
// Minimal context/state updater. Accepts --context <path>
$contextPath = 'wp-content/uploads/smartalloc/artifacts/context_pool.json';
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--context' && isset($argv[$i+1])) {
        $contextPath = $argv[$i+1];
        $i++;
    }
}
$fullPath = __DIR__ . '/../' . $contextPath;
@mkdir(dirname($fullPath), 0777, true);
$data = [];
if (is_file($fullPath)) {
    $json = file_get_contents($fullPath);
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
// Default PROJECT_STATE if not set
if (!isset($data['PROJECT_STATE'])) {
    $data['PROJECT_STATE'] = 'foundation';
}
$data['updated_at'] = date('c');
file_put_contents($fullPath, json_encode($data, JSON_PRETTY_PRINT));
fwrite(STDOUT, "update_state: updated {$contextPath}\n");

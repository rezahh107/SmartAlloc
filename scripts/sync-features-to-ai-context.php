<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Scripts;

use JsonException;
use RuntimeException;

/**
 * Sync feature statuses from features.json into ai_context.json.
 */
final class FeatureSyncException extends RuntimeException {}

if (! function_exists('wp_json_encode')) {
function wp_json_encode($data, $options = 0, $depth = 512) {
return json_encode($data, $options, $depth);
}
}

$root          = dirname(__DIR__);
$features_file = $argv[1] ?? $root . '/features.json';
$context_file  = $argv[2] ?? $root . '/ai_context.json';

try {
$features = read_json($features_file);
$context  = read_json($context_file);

$map = [];
foreach ($features['features'] ?? [] as $f) {
if (! isset($f['name'], $f['status'])) {
continue;
}
$map[ $f['name'] ] = $f['status'];
}
ksort($map, SORT_STRING);

$context['features']        = $map;
$context['last_update_utc'] = gmdate('Y-m-d\TH:i:s\Z');

$json = wp_json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
file_put_contents($context_file, $json);
} catch (FeatureSyncException $e) {
fwrite(STDERR, 'Feature sync failed: ' . $e->getMessage() . PHP_EOL);
exit(1);
}

function read_json(string $file): array {
if (! is_file($file)) {
throw new FeatureSyncException("Missing file: {$file}");
}
try {
$raw = (string) file_get_contents($file);
return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
throw new FeatureSyncException("Invalid JSON in {$file}");
}
}


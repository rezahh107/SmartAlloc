#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(0);
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

function iso(): string
{
    return date('c');
}

$root = dirname(__DIR__);
$artifacts = $root . '/artifacts';
$schemaDir = $artifacts . '/schema';
ensure_dir($schemaDir);

$items = [];

$paths = [];
$cov = $artifacts . '/coverage/coverage.json';
if (is_file($cov)) {
    $paths[] = $cov;
}
foreach (['qa', 'dist', 'i18n'] as $dir) {
    foreach (glob($artifacts . '/' . $dir . '/*.json') ?: [] as $f) {
        $paths[] = $f;
    }
}

foreach ($paths as $p) {
    $raw = (string)file_get_contents($p);
    $data = json_decode($raw, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        $items[] = ['path' => substr($p, strlen($root) + 1), 'issue' => 'Invalid JSON'];
        continue;
    }
    $base = basename($p);
    switch ($base) {
        case 'coverage.json':
            if (!isset($data['totals']['pct'])) {
                $items[] = ['path' => substr($p, strlen($root) + 1), 'issue' => 'Missing field', 'field' => 'totals.pct'];
            }
            break;
        case 'manifest.json':
            if (empty($data['entries']) || !is_array($data['entries'])) {
                $items[] = ['path' => substr($p, strlen($root) + 1), 'issue' => 'Missing field', 'field' => 'entries'];
            }
            break;
        case 'sbom.json':
            if (empty($data['packages']) || !is_array($data['packages'])) {
                $items[] = ['path' => substr($p, strlen($root) + 1), 'issue' => 'Missing field', 'field' => 'packages'];
            }
            break;
        case 'go-no-go.json':
            if (!isset($data['verdict'])) {
                $items[] = ['path' => substr($p, strlen($root) + 1), 'issue' => 'Missing field', 'field' => 'verdict'];
            }
            break;
        default:
            // no-op
            break;
    }
}

$out = [
    'generated_at' => iso(),
    'warnings' => count($items),
    'items' => $items,
];

file_put_contents($schemaDir . '/schema-validate.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo '[schema-validate] warnings=' . $out['warnings'] . "\n";
exit(0);


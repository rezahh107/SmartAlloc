#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dir = $root . '/.wordpress-org';
$result = ['assets' => [], 'warnings' => []];

if (!is_dir($dir)) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$types = ['icon' => [], 'banner' => [], 'screenshot' => [], 'other' => []];
$entries = scandir($dir) ?: [];
foreach ($entries as $file) {
    if ($file === '.' || $file === '..') { continue; }
    $path = $dir . '/' . $file;
    if (!is_file($path)) { continue; }
    $info = ['file' => $file, 'type' => 'other', 'bytes' => filesize($path) ?: 0];
    if (function_exists('getimagesize')) {
        $dim = @getimagesize($path);
        if ($dim) { $info['width'] = $dim[0]; $info['height'] = $dim[1]; }
    }
    if (preg_match('/^icon-/i', $file)) { $info['type'] = 'icon'; }
    elseif (preg_match('/^banner-/i', $file)) { $info['type'] = 'banner'; }
    elseif (preg_match('/^screenshot-/i', $file)) { $info['type'] = 'screenshot'; }
    $result['assets'][] = $info;
    $types[$info['type']][$file] = $info;
    if ($info['bytes'] > 1000000) { $result['warnings'][] = $file . ' is large'; }
}

if (count($types['icon']) > 0 && count($types['icon']) < 2) { $result['warnings'][] = 'icon missing variants'; }
if (count($types['banner']) > 0 && count($types['banner']) < 2) { $result['warnings'][] = 'banner missing variants'; }
if (empty($types['screenshot'])) { $result['warnings'][] = 'no screenshots found'; }

$table = buildTable($result['assets']);
if ($table !== '') {
    echo $table . PHP_EOL;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(0);

function buildTable(array $assets): string
{
    if (empty($assets)) { return ''; }
    $out = [];
    $out[] = str_pad('file', 30) . str_pad('bytes', 10) . 'dimensions';
    foreach ($assets as $a) {
        $dim = isset($a['width']) ? $a['width'] . 'x' . $a['height'] : '?';
        $out[] = str_pad($a['file'], 30) . str_pad((string) $a['bytes'], 10) . $dim;
    }
    return implode(PHP_EOL, $out);
}

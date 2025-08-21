<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$result = ['readme' => [], 'assets' => []];

$readmeFile = $root . '/readme.txt';
$readme = [
    'missing_headers' => [],
    'short_description' => true,
    'sections' => [],
    'ok' => true,
];
if (!is_file($readmeFile)) {
    $readme['ok'] = false;
    $readme['missing'] = true;
} else {
    $content = (string)file_get_contents($readmeFile);
    $lines = preg_split('/\r?\n/', $content);
    $headers = [];
    $idx = 1; // skip title
    for (; $idx < count($lines); $idx++) {
        $line = $lines[$idx];
        if ($line === '') { $idx++; break; }
        if (strpos($line, ':') !== false) {
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $headers[$k] = $v;
        }
    }
    $req = ['Requires at least','Tested up to','Stable tag','Requires PHP'];
    foreach ($req as $h) { if (!isset($headers[$h]) || $headers[$h] === '') { $readme['missing_headers'][] = $h; } }
    $short = '';
    for (; $idx < count($lines); $idx++) {
        $line = trim($lines[$idx]);
        if ($line !== '') { $short = $line; break; }
    }
    if ($short === '' || strlen($short) > 150) {
        $readme['short_description'] = false;
    }
    if (preg_match_all('/^==\s*(.+?)\s*==/m', $content, $matches, PREG_SET_ORDER)) {
        $positions = [];
        foreach ($matches as $m) {
            $positions[] = ['name' => $m[1], 'pos' => strpos($content, $m[0])];
        }
        for ($i=0; $i<count($positions); $i++) {
            $start = $positions[$i]['pos'] + strlen($positions[$i]['name']) + 4;
            $end = $positions[$i+1]['pos'] ?? strlen($content);
            $linesCount = substr_count(substr($content, $start, $end-$start), "\n");
            $readme['sections'][$positions[$i]['name']] = $linesCount;
            if ($linesCount > 400) {
                $readme['ok'] = false;
            }
        }
    }
    if ($readme['missing_headers'] || !$readme['short_description']) {
        $readme['ok'] = false;
    }
}
$result['readme'] = $readme;

$assetsDir = $root . '/.wordpress-org';
$assets = ['present' => false];
if (is_dir($assetsDir)) {
    $assets['present'] = true;
    $required = [
        'banner-772x250' => 1000000,
        'banner-1544x500' => 1500000,
        'icon-128x128' => 100000,
        'icon-256x256' => 200000,
    ];
    foreach ($required as $base => $max) {
        $found = false;
        foreach (['.png','.jpg','.jpeg','.svg'] as $ext) {
            $path = "$assetsDir/$base$ext";
            if (is_file($path)) {
                $size = filesize($path);
                $assets['files'][$base.$ext] = ['size' => $size, 'ok' => $size <= $max];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $assets['files'][$base] = ['missing' => true];
        }
    }
}
$result['assets'] = $assets;

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);

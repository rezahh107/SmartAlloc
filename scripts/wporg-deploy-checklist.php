<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$opts = getopt('', ['dir:']);
$base = $opts['dir'] ?? ($root . '/wporg');
$outDir = $root . '/artifacts/wporg';
@mkdir($outDir, 0777, true);
$result = ['warnings' => [], 'versions' => ['plugin' => null, 'readme' => null, 'tag' => null]];

$trunk = $base . '/trunk';
$assets = $base . '/assets';
$tagsDir = $base . '/tags';

if (!is_dir($trunk)) {
    $result['warnings'][] = 'trunk_missing';
} else {
    $readme = $trunk . '/readme.txt';
    if (!is_file($readme)) {
        $result['warnings'][] = 'readme_missing';
    } else {
        $content = (string) file_get_contents($readme);
        if (preg_match('/Stable tag:\s*(.+)/i', $content, $m)) {
            $result['versions']['readme'] = trim($m[1]);
        }
    }
    $pluginFile = null;
    foreach (glob($trunk . '/*.php') as $f) {
        $data = (string) file_get_contents($f);
        if (stripos($data, 'Plugin Name:') !== false) { $pluginFile = $f; break; }
    }
    if ($pluginFile && is_file($pluginFile)) {
        $data = (string) file_get_contents($pluginFile);
        if (preg_match('/Version:\s*(.+)/i', $data, $m)) {
            $result['versions']['plugin'] = trim($m[1]);
        }
    }
}

if (is_dir($tagsDir)) {
    $tags = array_values(array_filter(scandir($tagsDir) ?: [], fn($n) => $n !== '.' && $n !== '..'));
    $result['versions']['tag'] = $tags[0] ?? null;
}

if ($result['versions']['plugin'] !== null && $result['versions']['readme'] !== null && $result['versions']['plugin'] !== $result['versions']['readme']) {
    $result['warnings'][] = 'stable_tag_mismatch';
}

if (!is_dir($assets)) {
    $result['warnings'][] = 'assets_missing';
} else {
    $required = [
        'banner-772x250' => [772, 250],
        'banner-1544x500' => [1544, 500],
        'icon-128x128' => [128, 128],
        'icon-256x256' => [256, 256],
    ];
    foreach ($required as $baseName => $dim) {
        $found = false;
        foreach (['.png', '.jpg', '.jpeg', '.gif'] as $ext) {
            $path = $assets . '/' . $baseName . $ext;
            if (is_file($path)) {
                $found = true;
                if (function_exists('getimagesize')) {
                    $info = @getimagesize($path);
                    if (!$info || $info[0] !== $dim[0] || $info[1] !== $dim[1]) {
                        $result['warnings'][] = $baseName . '_bad_size';
                    }
                }
                break;
            }
        }
        if (!$found) {
            $result['warnings'][] = $baseName . '_missing';
        }
    }
}

file_put_contents($outDir . '/deploy-checklist.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

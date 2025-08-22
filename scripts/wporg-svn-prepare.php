<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$distPath = $argv[1] ?? '';
$version = $argv[2] ?? '';
$outDir = $root . '/artifacts/wporg';
@mkdir($outDir, 0777, true);
$result = [
    'input' => ['dist' => $distPath, 'version' => $version],
    'output' => ['dir' => $outDir],
    'validation' => [
        'plugin_header' => [],
        'readme' => [],
        'assets' => [],
    ],
    'status' => 'ok',
];

if ($distPath === '' || $version === '') {
    $result['status'] = 'missing_args';
    file_put_contents($outDir . '/prepare.json', json_encode($result, JSON_PRETTY_PRINT) . "\n");
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$tmp = $outDir . '/tmp-dist';
if (is_dir($tmp)) {
    deleteDir($tmp);
}
@mkdir($tmp, 0777, true);

$distReal = realpath($distPath);
if ($distReal === false) {
    $result['status'] = 'dist_missing';
} elseif (is_file($distReal) && preg_match('/\.zip$/i', $distReal)) {
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($distReal) === true) {
            $zip->extractTo($tmp);
            $zip->close();
        } else {
            $result['status'] = 'zip_open_failed';
        }
    } else {
        $result['status'] = 'zip_extension_missing';
    }
} elseif (is_dir($distReal)) {
    copyDir($distReal, $tmp);
} else {
    $result['status'] = 'dist_invalid';
}

$trunk = $outDir . '/trunk';
$tag = $outDir . '/tags/' . $version;
$assetsOut = $outDir . '/assets';
@mkdir($outDir . '/tags', 0777, true);

if (is_dir($tmp)) {
    copyDir($tmp, $trunk);
    copyDir($tmp, $tag);
}

$assetsSrc = $root . '/.wordpress-org';
if (is_dir($assetsSrc)) {
    copyDir($assetsSrc, $assetsOut);
}

// Plugin header validation
$pluginFile = null;
if (is_dir($tmp)) {
    foreach (glob($tmp . '/*.php') as $file) {
        $content = (string)file_get_contents($file);
        if (stripos($content, 'Plugin Name:') !== false) {
            $pluginFile = $file;
            break;
        }
    }
}
$header = ['name' => null, 'version' => null, 'text_domain' => null, 'ok' => false];
if ($pluginFile && is_file($pluginFile)) {
    $data = (string)file_get_contents($pluginFile);
    if (preg_match('/Plugin Name:\s*(.+)/i', $data, $m)) {
        $header['name'] = trim($m[1]);
    }
    if (preg_match('/Version:\s*(.+)/i', $data, $m)) {
        $header['version'] = trim($m[1]);
    }
    if (preg_match('/Text Domain:\s*(.+)/i', $data, $m)) {
        $header['text_domain'] = trim($m[1]);
    }
    $header['ok'] = $header['name'] && $header['version'] && $header['text_domain'] && ($header['version'] === $version);
}
$result['validation']['plugin_header'] = $header;

// readme stable tag
$readmeFile = $tmp . '/readme.txt';
$readme = ['stable_tag' => null, 'ok' => false];
if (is_file($readmeFile)) {
    $content = (string)file_get_contents($readmeFile);
    if (preg_match('/Stable tag:\s*(.+)/i', $content, $m)) {
        $readme['stable_tag'] = trim($m[1]);
    }
    $readme['ok'] = $readme['stable_tag'] === $version && $header['version'] === $version;
}
$result['validation']['readme'] = $readme;

// assets sanity
$assets = ['files' => [], 'ok' => true];
if (is_dir($assetsSrc)) {
    $required = [
        'banner-772x250' => 1000000,
        'banner-1544x500' => 1500000,
        'icon-128x128' => 100000,
        'icon-256x256' => 200000,
    ];
    foreach ($required as $base => $max) {
        $found = false;
        foreach (['.png','.jpg','.jpeg','.svg'] as $ext) {
            $path = "$assetsSrc/$base$ext";
            if (is_file($path)) {
                $size = filesize($path) ?: 0;
                $assets['files'][$base.$ext] = ['size' => $size, 'ok' => $size <= $max];
                if ($size > $max) { $assets['ok'] = false; }
                $found = true;
                break;
            }
        }
        if (!$found) {
            $assets['files'][$base] = ['missing' => true];
            $assets['ok'] = false;
        }
    }
} else {
    $assets['ok'] = false;
}
$result['validation']['assets'] = $assets;

deleteDir($tmp);

file_put_contents($outDir . '/prepare.json', json_encode($result, JSON_PRETTY_PRINT) . "\n");
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);

function copyDir(string $src, string $dst): void {
    if (!is_dir($src)) { return; }
    @mkdir($dst, 0777, true);
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iter as $info) {
        $rel = substr($info->getPathname(), strlen($src) + 1);
        $target = $dst . '/' . $rel;
        if ($info->isDir()) {
            if (!is_dir($target)) { @mkdir($target, 0777, true); }
        } else {
            @copy($info->getPathname(), $target);
        }
    }
}

function deleteDir(string $dir): void {
    if (!is_dir($dir)) { return; }
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $info) {
        if ($info->isDir()) {
            @rmdir($info->getPathname());
        } else {
            @unlink($info->getPathname());
        }
    }
    @rmdir($dir);
}

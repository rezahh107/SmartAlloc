#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
$pluginFile = $root . '/smart-alloc.php';
$version = 'dev';
if (preg_match('/^\s*Version:\s*(.+)$/mi', file_get_contents($pluginFile), $m)) {
    $version = trim($m[1]);
}

$zipPath = $root . "/smartalloc-{$version}.zip";

$exclude = [
    '.git',
    '.github',
    'node_modules',
    'tests',
    'docs',
    'perf',
    'e2e',
    'qa',
    'tools',
    'scripts',
    'vendor/bin',
];

$zip = new ZipArchive();
if (true !== $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    fwrite(STDERR, "Unable to create ZIP\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    $path = str_replace($root . '/', '', $file->getPathname());
    foreach ($exclude as $ex) {
        if (str_starts_with($path, $ex)) {
            continue 2;
        }
    }
    if ($file->isDir()) {
        continue;
    }
    $zip->addFile($file->getPathname(), $path);
}
$zip->close();

$checksum = hash_file('sha256', $zipPath);
file_put_contents($zipPath . '.sha256', $checksum);

echo $zipPath . "\n";

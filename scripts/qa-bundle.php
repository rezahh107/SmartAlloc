<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$outDir = $root . '/artifacts/qa';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$outZip = $outDir . '/qa-bundle.zip';

$files = [];
$addIfExists = function (string $path) use (&$files) {
    if (is_file($path)) {
        $files[$path] = basename($path);
    }
};

$addIfExists($root . '/qa-report.json');
$addIfExists($root . '/qa-report.html');
$addIfExists($root . '/rest-violations.json');
$addIfExists($root . '/sql-violations.json');

function latest_in_dir(string $dir): ?string {
    if (!is_dir($dir)) {
        return null;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    $latest = null;
    $mtime = 0;
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $t = $file->getMTime();
            if ($t > $mtime) {
                $mtime = $t;
                $latest = $file->getPathname();
            }
        }
    }
    return $latest;
}

$latestAxe = latest_in_dir($root . '/artifacts/axe');
$latestLh = latest_in_dir($root . '/artifacts/lighthouse');
if ($latestAxe) {
    $files[$latestAxe] = basename($latestAxe);
}
if ($latestLh) {
    $files[$latestLh] = basename($latestLh);
}

$zip = new ZipArchive();
if ($zip->open($outZip, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    foreach ($files as $path => $local) {
        $zip->addFile($path, $local);
    }
    $zip->close();
}
if (!file_exists($outZip)) {
    touch($outZip);
}

echo $outZip . PHP_EOL;
exit(0);

<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$pluginFile = $root . '/smart-alloc.php';
$contents = file_get_contents($pluginFile);
if ($contents === false) {
    fwrite(STDERR, "Unable to read plugin file\n");
    exit(1);
}
if (!preg_match('/^Version:\s*(.+)$/m', $contents, $m)) {
    fwrite(STDERR, "Plugin version not found\n");
    exit(1);
}
$version = trim($m[1]);

$buildDir = $root . '/build';
$tempDir = $buildDir . '/smartalloc';
if (is_dir($tempDir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($tempDir);
}
if (!is_dir($buildDir) && !mkdir($buildDir) && !is_dir($buildDir)) {
    fwrite(STDERR, "Unable to create build directory\n");
    exit(1);
}
mkdir($tempDir);

$files = [
    'smart-alloc.php',
    'readme.txt',
    'README.md',
    'SECURITY.md',
    'CHANGELOG.md',
    'LICENSE',
];
$dirs = ['src', 'assets', 'languages'];

foreach ($files as $file) {
    $src = $root . '/' . $file;
    if (file_exists($src)) {
        copy($src, $tempDir . '/' . $file);
    }
}
foreach ($dirs as $dir) {
    $srcDir = $root . '/' . $dir;
    if (!is_dir($srcDir)) {
        continue;
    }
    $destDir = $tempDir . '/' . $dir;
    mkdir($destDir, 0777, true);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $relative = substr($item->getPathname(), strlen($srcDir) + 1);
        $destPath = $destDir . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($destPath)) {
                mkdir($destPath, 0777, true);
            }
        } else {
            $dir = dirname($destPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            copy($item->getPathname(), $destPath);
        }
    }
}

$zipPath = $buildDir . '/smartalloc-' . $version . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to create zip\n");
    exit(1);
}
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    $path = substr($file->getPathname(), strlen($tempDir) + 1);
    $zip->addFile($file->getPathname(), 'smartalloc/' . $path);
}
$zip->close();

// cleanup temp directory
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $file) {
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
}
rmdir($tempDir);

echo "Created dist: {$zipPath}\n";

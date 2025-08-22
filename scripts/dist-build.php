<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$src  = $argv[1] ?? $root;
$destBase = $argv[2] ?? ($root . '/dist');
$dest = rtrim($destBase, '/\\') . '/SmartAlloc';

if (is_dir($dest)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($dest);
}

$files = [];
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iter as $info) {
    $rel = substr($info->getPathname(), strlen($src) + 1);
    $relPosix = str_replace('\\', '/', $rel);
    if (shouldExclude($relPosix, $info->isDir())) {
        continue;
    }
    $files[$relPosix] = $info;
}
ksort($files);

foreach ($files as $rel => $info) {
    $target = $dest . '/' . $rel;
    if ($info->isDir()) {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }
        chmod($target, 0755);
        continue;
    }
    $dir = dirname($target);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $content = file_get_contents($info->getPathname());
    if ($content !== false && strpos($content, "\0") === false) {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
    }
    file_put_contents($target, $content);
    chmod($target, 0644);
}

exit(0);

function shouldExclude(string $rel, bool $isDir): bool {
    $patterns = [
        '#^\.git(/|$)#',
        '#^node_modules(/|$)#',
        '#^vendor/[^/]*dev[^/]*(/|$)#i',
        '#^tests(/|$)#',
        '#^\.github(/|$)#',
        '#^artifacts(/|$)#',
        '#^coverage(/|$)#',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $rel)) {
            return true;
        }
    }
    if (!$isDir) {
        if (preg_match('/\.md$/i', $rel) && strtolower($rel) !== 'readme.txt') {
            return true;
        }
    }
    return false;
}

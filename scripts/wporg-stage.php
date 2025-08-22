<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$tag = $argv[1] ?? null;
if ($tag === null) {
    fwrite(STDERR, "usage: php scripts/wporg-stage.php <tag>\n");
    exit(1);
}
$version = ltrim($tag, 'v');
$readme = $root . '/readme.txt';
$stable = null;
if (is_file($readme)) {
    if (preg_match('/^Stable tag:\s*(.+)$/mi', (string)file_get_contents($readme), $m)) {
        $stable = trim($m[1]);
    }
}
if ($stable !== $version) {
    fwrite(STDERR, "Stable tag mismatch\n");
    exit(1);
}
$tmp = sys_get_temp_dir() . '/wporg-' . bin2hex(random_bytes(4));
$trunk = $tmp . '/trunk';
$assets = $tmp . '/assets';
@mkdir($trunk, 0777, true);
@mkdir($assets, 0777, true);
$src = is_dir($root . '/dist/SmartAlloc') ? $root . '/dist/SmartAlloc' : $root;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
foreach ($it as $info) {
    $rel = substr($info->getPathname(), strlen($src) + 1);
    $target = $trunk . '/' . $rel;
    if ($info->isDir()) {
        @mkdir($target, 0777, true);
    } else {
        @mkdir(dirname($target), 0777, true);
        copy($info->getPathname(), $target);
    }
}
if (is_dir($root . '/assets')) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/assets', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $info) {
        $rel = substr($info->getPathname(), strlen($root . '/assets') + 1);
        $target = $assets . '/' . $rel;
        if ($info->isDir()) {
            @mkdir($target, 0777, true);
        } else {
            @mkdir(dirname($target), 0777, true);
            copy($info->getPathname(), $target);
        }
    }
}
$summary = [
    'staged_path' => $tmp,
    'generated_at_utc' => gmdate('c'),
];
file_put_contents($root . '/artifacts/release/wporg-stage.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit(0);

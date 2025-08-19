<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$pluginFile = $root . '/smart-alloc.php';
$composerFile = $root . '/composer.json';

$headersRequired = [
    'Plugin Name',
    'Version',
    'Text Domain',
    'Requires at least',
    'Tested up to',
    'Requires PHP',
];

$contents = file_get_contents($pluginFile);
if ($contents === false) {
    fwrite(STDERR, "Unable to read plugin file\n");
    exit(1);
}

foreach ($headersRequired as $header) {
    if (!preg_match('/^' . preg_quote($header, '/') . ':.*$/m', $contents)) {
        fwrite(STDERR, "Missing plugin header: {$header}\n");
        exit(1);
    }
}

if (!preg_match('/^Version:\s*(.+)$/m', $contents, $m)) {
    fwrite(STDERR, "Unable to read plugin version\n");
    exit(1);
}
$pluginVersion = trim($m[1]);

$composer = json_decode((string) file_get_contents($composerFile), true);
if (!is_array($composer) || empty($composer['version'])) {
    fwrite(STDERR, "Unable to read composer version\n");
    exit(1);
}
if ($composer['version'] !== $pluginVersion) {
    fwrite(STDERR, "Version mismatch between plugin ({$pluginVersion}) and composer ({$composer['version']})\n");
    exit(1);
}

if (strpos($contents, "load_plugin_textdomain('smartalloc'") === false) {
    fwrite(STDERR, "Text domain not loaded correctly\n");
    exit(1);
}

// Build dist and inspect
$buildScript = $root . '/tools/build-dist.php';
$cmd = escapeshellcmd(PHP_BINARY . ' ' . $buildScript);
exec($cmd, $out, $exit); // build the zip
if ($exit !== 0) {
    fwrite(STDERR, "Failed to build dist\n");
    exit(1);
}

$zipPath = $root . '/build/smartalloc-' . $pluginVersion . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    fwrite(STDERR, "Unable to open dist zip\n");
    exit(1);
}
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (preg_match('#/(?:vendor|tests|node_modules)/#', $name)) {
        $zip->close();
        fwrite(STDERR, "Dist contains disallowed paths: {$name}\n");
        exit(1);
    }
}
$zip->close();

echo "Preflight checks passed\n";
exit(0);

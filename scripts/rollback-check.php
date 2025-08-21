<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$manifestPath = $root . '/artifacts/dist/manifest.json';
$pluginFile = $root . '/smart-alloc.php';

$manifest = [];
if (is_file($manifestPath)) {
    $manifest = json_decode((string)file_get_contents($manifestPath), true);
}

$version = null;
if (is_file($pluginFile)) {
    $contents = (string)file_get_contents($pluginFile);
    if (preg_match('/^\s*Version:\s*(.+)$/mi', $contents, $m)) {
        $version = trim($m[1]);
    }
}

$prev = null;
if ($version !== null) {
    $base = preg_replace('/-.*/', '', $version);
    $parts = explode('.', $base);
    if (count($parts) === 3) {
        $parts[2] = (string)max(0, ((int)$parts[2]) - 1);
        $prev = implode('.', $parts);
    }
}

$lines = [];
$lines[] = '# Rollback Check';
if ($version !== null) {
    $lines[] = 'Current version: ' . $version;
}
if ($prev !== null) {
    $artifact = 'smart-alloc-' . $prev . '.zip';
    $lines[] = 'Fetch previous GA artifact:';
    $lines[] = 'curl -LO https://downloads.wordpress.org/plugin/smart-alloc.' . $prev . '.zip';
    $lines[] = 'unzip -q smart-alloc.' . $prev . '.zip -d rollback-' . $prev;
    $entries = $manifest['entries'] ?? ($manifest['files'] ?? []);
    if (!empty($entries)) {
        $lines[] = 'cd rollback-' . $prev;
        $lines[] = 'sha256sum --check <<\'EOF\'';
        foreach ($entries as $f) {
            $path = $f['path'] ?? '';
            $sha = $f['sha256'] ?? '';
            if ($path !== '' && $sha !== '') {
                $lines[] = $sha . '  ' . $path;
            }
        }
        $lines[] = 'EOF';
    } else {
        $lines[] = '# manifest.json missing or empty';
    }
} else {
    $lines[] = 'Plugin version not detected.';
}

echo implode("\n", $lines) . "\n";
exit(0);

<?php
declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__);
$warnings = [];

$pluginVersion = '';
$pluginFile = null;
foreach (glob($root . '/*.php') as $file) {
    $chunk = (string)file_get_contents($file, false, null, 0, 8192);
    if (preg_match('/Plugin Name\s*:/i', $chunk)) {
        $pluginFile = $file;
        if (preg_match('/^\s*Version:\s*(.+)$/mi', $chunk, $m)) {
            $pluginVersion = trim($m[1]);
        }
        break;
    }
}
if ($pluginFile === null) {
    $warnings[] = 'plugin_file_missing';
}

$readmeStable = '';
$readme = $root . '/readme.txt';
if (!is_file($readme)) {
    $warnings[] = 'readme_missing';
} else {
    $content = (string)file_get_contents($readme);
    if (!preg_match('/^Stable tag:\s*(.+)$/mi', $content, $m)) {
        $warnings[] = 'stable_tag_missing';
    } else {
        $readmeStable = trim($m[1]);
    }
}

$changelogVersion = '';
$changelog = $root . '/CHANGELOG.md';
if (is_file($changelog)) {
    $ch = (string)file_get_contents($changelog);
    if (preg_match('/^##\s*([^\s]+)\s*$/m', $ch, $m)) {
        $changelogVersion = trim($m[1]);
    }
}

if ($pluginVersion !== '' && $readmeStable !== '' && $pluginVersion !== $readmeStable) {
    $warnings[] = 'plugin_vs_readme';
}
if ($readmeStable !== '' && $changelogVersion !== '' && $readmeStable !== $changelogVersion) {
    $warnings[] = 'readme_vs_changelog';
}
if ($pluginVersion !== '' && $changelogVersion !== '' && $pluginVersion !== $changelogVersion) {
    $warnings[] = 'plugin_vs_changelog';
}

$outDir = $root . '/artifacts/dist';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$result = [
    'plugin_version' => $pluginVersion,
    'readme_stable_tag' => $readmeStable,
    'changelog_version' => $changelogVersion,
    'warnings' => $warnings,
];
file_put_contents($outDir . '/version-coherence.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit(0);

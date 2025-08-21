<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$mismatches = [];

$pluginFile = null;
foreach (glob($root . '/*.php') as $file) {
    $chunk = (string)file_get_contents($file, false, null, 0, 8192);
    if (preg_match('/Plugin Name\s*:/i', $chunk)) {
        $pluginFile = $file;
        break;
    }
}

$version = '';
$textDomain = '';
if ($pluginFile === null) {
    $mismatches[] = ['type' => 'plugin_file_missing'];
} else {
    $header = (string)file_get_contents($pluginFile, false, null, 0, 8192);
    $required = ['Plugin Name', 'Version', 'Requires at least', 'Tested up to', 'Requires PHP', 'Text Domain'];
    foreach ($required as $name) {
        if (!preg_match('/' . preg_quote($name, '/') . '\s*:\s*(.+)/i', $header, $m)) {
            $mismatches[] = ['type' => 'header_missing', 'field' => $name];
            continue;
        }
        $val = trim($m[1]);
        if ($name === 'Version') {
            $version = $val;
        } elseif ($name === 'Text Domain') {
            $textDomain = $val;
            if ($textDomain === '') {
                $mismatches[] = ['type' => 'text_domain_empty'];
            }
        }
    }
}

$readme = $root . '/readme.txt';
if (!is_file($readme)) {
    $mismatches[] = ['type' => 'readme_missing'];
    $stable = '';
} else {
    $content = (string)file_get_contents($readme);
    if (stripos($content, '== Changelog ==') === false || stripos($content, '== Description ==') === false) {
        $mismatches[] = ['type' => 'readme_sections'];
    }
    if (!preg_match('/^Stable tag:\s*(.+)$/mi', $content, $m)) {
        $mismatches[] = ['type' => 'stable_tag_missing'];
        $stable = '';
    } else {
        $stable = trim($m[1]);
        if ($version !== '' && $version !== $stable) {
            $mismatches[] = ['type' => 'version_mismatch', 'plugin' => $version, 'readme' => $stable];
        }
    }
    if ($textDomain === '') {
        // Already flagged empty, but ensure readme mentions same domain? Not required.
    }
}

$changelog = $root . '/CHANGELOG.md';
if (is_file($changelog) && $version !== '') {
    $chContent = (string)file_get_contents($changelog);
    if (preg_match('/^##\s*' . preg_quote($version, '/') . '\b/m', $chContent) === 0) {
        $mismatches[] = ['type' => 'changelog_missing_entry', 'version' => $version];
    }
}

$ok = empty($mismatches);
$result = ['summary' => ['ok' => $ok, 'mismatches' => $mismatches]];

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);

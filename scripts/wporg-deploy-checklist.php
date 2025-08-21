<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$outDir = $root . '/artifacts/wporg';
@mkdir($outDir, 0777, true);
$result = ['tag' => null, 'assets' => [], 'version' => [], 'checksums' => [], 'links' => []];

$tagDir = $outDir . '/tags';
if (is_dir($tagDir)) {
    $entries = array_values(array_filter(scandir($tagDir) ?: [], static function ($n) {
        return $n !== '.' && $n !== '..';
    }));
    $result['tag'] = $entries[0] ?? null;
}

$trunk = $outDir . '/trunk';
$assetsDir = $outDir . '/assets';

// Version coherence
$pluginFile = null;
if (is_dir($trunk)) {
    foreach (glob($trunk . '/*.php') as $file) {
        $content = (string)file_get_contents($file);
        if (stripos($content, 'Plugin Name:') !== false) {
            $pluginFile = $file;
            break;
        }
    }
}
$pluginVersion = null;
if ($pluginFile && is_file($pluginFile)) {
    $data = (string)file_get_contents($pluginFile);
    if (preg_match('/Version:\s*(.+)/i', $data, $m)) {
        $pluginVersion = trim($m[1]);
    }
}
$readmeFile = $trunk . '/readme.txt';
$readmeVersion = null;
if (is_file($readmeFile)) {
    $content = (string)file_get_contents($readmeFile);
    if (preg_match('/Stable tag:\s*(.+)/i', $content, $m)) {
        $readmeVersion = trim($m[1]);
    }
}
$result['version'] = ['plugin' => $pluginVersion, 'readme' => $readmeVersion, 'tag' => $result['tag']];

// Assets
if (is_dir($assetsDir)) {
    $files = scandir($assetsDir) ?: [];
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') { continue; }
        $path = $assetsDir . '/' . $f;
        if (is_file($path)) {
            $result['assets'][$f] = filesize($path) ?: 0;
        }
    }
}

// Checksums
$distZip = $root . '/artifacts/dist/SmartAlloc-normalized.zip';
if (is_file($distZip)) {
    $result['checksums']['SmartAlloc-normalized.zip'] = hash_file('sha256', $distZip);
}
$manifest = $root . '/artifacts/dist/manifest.json';
if (is_file($manifest)) {
    $result['checksums']['manifest.json'] = hash_file('sha256', $manifest);
    $result['links'][] = 'artifacts/dist/manifest.json';
}
$sbom = $root . '/artifacts/dist/sbom.json';
if (is_file($sbom)) {
    $result['checksums']['sbom.json'] = hash_file('sha256', $sbom);
    $result['links'][] = 'artifacts/dist/sbom.json';
}
$goNoGo = $root . '/artifacts/qa/go-no-go.json';
if (is_file($goNoGo)) {
    $result['links'][] = 'artifacts/qa/go-no-go.json';
}
$qaReport = $root . '/artifacts/qa/qa-report.json';
if (is_file($qaReport)) {
    $result['links'][] = 'artifacts/qa/qa-report.json';
}
$relNotes = $root . '/artifacts/dist/release-notes.md';
if (is_file($relNotes)) {
    $result['links'][] = 'artifacts/dist/release-notes.md';
}
$relDraft = $root . '/artifacts/dist/release-draft.md';
if (is_file($relDraft)) {
    $result['links'][] = 'artifacts/dist/release-draft.md';
}

// Build markdown
$md = "# WP.org Deploy Checklist\n\n";
$md .= "- [ ] Tag name: " . ($result['tag'] ?? 'n/a') . "\n";
$md .= "- [ ] Assets present:" . (empty($result['assets']) ? ' none' : '') . "\n";
foreach ($result['assets'] as $name => $size) {
    $md .= "  - $name ($size bytes)\n";
}
$md .= "- [ ] Version coherence:\n";
$md .= "  - Plugin header: " . ($pluginVersion ?? 'n/a') . "\n";
$md .= "  - readme Stable tag: " . ($readmeVersion ?? 'n/a') . "\n";
$md .= "  - Tag directory: " . ($result['tag'] ?? 'n/a') . "\n";
$md .= "- [ ] Checksums:\n";
foreach ($result['checksums'] as $file => $hash) {
    $md .= "  - $file SHA256: $hash\n";
}
$md .= "- [ ] Preflight artifacts:\n";
foreach ($result['links'] as $link) {
    $md .= "  - $link\n";
}
file_put_contents($outDir . '/DEPLOY_CHECKLIST.md', $md);

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);

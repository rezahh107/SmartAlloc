<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$changelog = $root . '/CHANGELOG.md';
$version = '';
$date = '';
$highlights = [];
if (is_file($changelog)) {
    $content = (string)file_get_contents($changelog);
    if (preg_match('/^##\s*\[?([^\]\s]+)\]?\s*-\s*(\d{4}-\d{2}-\d{2})\s*\n(.*?)(?:\n##\s|\z)/s', $content, $m)) {
        $version = $m[1];
        $date = $m[2];
        $body = trim($m[3]);
        foreach (preg_split('/\r?\n/', $body) as $line) {
            if (preg_match('/^\s*[-*]\s*(.+)/', $line, $lm)) {
                $highlights[] = trim($lm[1]);
            }
        }
    }
}

$artifacts = [];
$paths = [
    'manifest' => $root . '/artifacts/dist/manifest.json',
    'sbom' => $root . '/artifacts/dist/sbom.json',
];
foreach ($paths as $name => $path) {
    if (is_file($path)) {
        $artifacts[$name] = [
            'present' => true,
            'sha256' => hash_file('sha256', $path),
        ];
    } else {
        $artifacts[$name] = [
            'present' => false,
        ];
    }
}

$result = [
    'version' => $version,
    'date' => $date,
    'highlights' => $highlights,
    'artifacts' => $artifacts,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(0);

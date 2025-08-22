<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$enforce = in_array('--enforce', $argv, true);

function readJson(string $path): array {
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

$manifest = readJson($root . '/artifacts/dist/manifest.json');
$audit = readJson($root . '/artifacts/dist/audit.json');
$version = readJson($root . '/artifacts/dist/version-coherence.json');
$readme = readJson($root . '/artifacts/dist/readme-lint.json');
$sbom = readJson($root . '/artifacts/dist/sbom.json');

$summary = [
    'manifest_entries' => count($manifest['entries'] ?? []),
    'audit_warnings' => count($audit['warnings'] ?? []),
    'version_warnings' => count($version['warnings'] ?? []),
    'readme_warnings' => count($readme['warnings'] ?? []),
    'sbom_packages' => count($sbom['packages'] ?? []),
    'generated_at_utc' => gmdate('c'),
];

$outDir = $root . '/artifacts/release';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
file_put_contents($outDir . '/preflight.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

printf("Preflight summary\nManifest entries: %d\nAudit warnings: %d\nVersion warnings: %d\nReadme warnings: %d\nSBOM packages: %d\n",
    $summary['manifest_entries'],
    $summary['audit_warnings'],
    $summary['version_warnings'],
    $summary['readme_warnings'],
    $summary['sbom_packages']
);

$blocking = $summary['audit_warnings'] + $summary['version_warnings'] + $summary['readme_warnings'];
if ($enforce && $blocking > 0) {
    exit(1);
}
exit(0);

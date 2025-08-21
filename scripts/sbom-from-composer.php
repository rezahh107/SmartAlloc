<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$lockFile = $root . '/composer.lock';
$packages = [];
if (is_file($lockFile)) {
    $data = json_decode((string)file_get_contents($lockFile), true);
    foreach (['packages', 'packages-dev'] as $section) {
        foreach ($data[$section] ?? [] as $pkg) {
            $entry = [
                'name' => $pkg['name'] ?? '',
                'version' => $pkg['version'] ?? '',
                'license' => $pkg['license'][0] ?? '',
                'type' => 'composer',
            ];
            $sha = $pkg['dist']['sha256'] ?? ($pkg['dist']['shasum'] ?? '');
            if ($sha !== '') {
                $entry['checksum'] = ['algorithm' => 'sha256', 'hash' => $sha];
            }
            $packages[] = $entry;
        }
    }
}
$destDir = $root . '/artifacts/dist';
if (!is_dir($destDir)) {
    mkdir($destDir, 0777, true);
}
$sbomFile = $destDir . '/sbom.json';
file_put_contents($sbomFile, json_encode(['packages' => $packages], JSON_PRETTY_PRINT) . PHP_EOL);
$result = ['summary' => ['written' => is_file($sbomFile), 'count' => count($packages)]];
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);

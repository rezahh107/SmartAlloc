<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$denyList = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--deny=')) {
        $denyList = array_filter(array_map('trim', explode(',', substr($arg, 7))));
    }
}

$lockFile = $root . '/composer.lock';
if (!is_file($lockFile)) {
    echo json_encode(['error' => 'composer.lock not found']) . PHP_EOL;
    exit(0);
}

$data = json_decode((string)file_get_contents($lockFile), true);
$packages = [];
foreach (['packages', 'packages-dev'] as $section) {
    foreach ($data[$section] ?? [] as $pkg) {
        $licenses = $pkg['license'] ?? [];
        $licenses = is_array($licenses) ? $licenses : [$licenses];
        $status = 'ok';
        if (empty($licenses)) {
            $status = 'unknown';
        } elseif (!empty($denyList) && array_intersect($licenses, $denyList)) {
            $status = 'denied';
        }
        $packages[] = [
            'name' => $pkg['name'],
            'version' => $pkg['version'] ?? '',
            'licenses' => $licenses,
            'status' => $status,
        ];
    }
}

$total = count($packages);
$unknown = count(array_filter($packages, static fn($p) => $p['status'] === 'unknown'));
$denied = count(array_filter($packages, static fn($p) => $p['status'] === 'denied'));

$output = [
    'summary' => [
        'total' => $total,
        'unknown' => $unknown,
        'denied' => $denied,
    ],
    'packages' => $packages,
];

echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);

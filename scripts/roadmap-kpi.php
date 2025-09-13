<?php
declare(strict_types=1);

// Print KPI thresholds (and optionally evaluate simple booleans if provided)

$path = __DIR__ . '/../docs/ROADMAP-LIVE.json';
if (!file_exists($path)) {
    fwrite(STDERR, "Roadmap file not found: {$path}\n");
    exit(1);
}

$data = json_decode((string) file_get_contents($path), true);
if (!is_array($data)) {
    fwrite(STDERR, "Invalid roadmap JSON.\n");
    exit(1);
}

$kpis = $data['kpis'] ?? [];
echo "SmartAlloc — KPI Thresholds\n";
echo str_repeat('=', 40) . "\n";
foreach ($kpis as $group => $defs) {
    echo strtoupper((string) $group) . ":\n";
    foreach ($defs as $name => $target) {
        echo sprintf(" - %-24s %s\n", $name, (string) $target);
    }
}


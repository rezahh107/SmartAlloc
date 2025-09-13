<?php
declare(strict_types=1);

// Simple roadmap printer for CLI usage via composer

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

echo "SmartAlloc — ROADMAP LIVE\n";
echo str_repeat('=', 40) . "\n";
echo 'Phase: ' . ($data['phase'] ?? 'unknown') . "\n";
echo 'Updated: ' . ($data['updated_at'] ?? 'unknown') . "\n";
if (isset($data['progress'])) {
    $pct = (float) $data['progress'] * 100.0;
    echo 'Progress: ' . number_format($pct, 0) . "%\n";
}
echo "\nPriorities:\n";
foreach (($data['priorities'] ?? []) as $p) {
    $status = $p['status'] ?? 'n/a';
    $title  = $p['title'] ?? '—';
    $id     = $p['id'] ?? '';
    echo " - [{$status}] {$id} — {$title}\n";
}

echo "\nKPIs (thresholds)\n";
echo str_repeat('-', 24) . "\n";
foreach (($data['kpis'] ?? []) as $group => $k) {
    echo "{$group}: ";
    $pairs = [];
    foreach ($k as $key => $val) {
        $pairs[] = $key . '=' . (is_scalar($val) ? (string) $val : json_encode($val));
    }
    echo implode(', ', $pairs) . "\n";
}

if (isset($data['links']['canonical_doc'])) {
    echo "\nDoc: " . $data['links']['canonical_doc'] . "\n";
}


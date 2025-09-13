<?php
declare(strict_types=1);

// Fails if ROADMAP-LIVE.json older than N days (default 7)

$path = __DIR__ . '/../docs/ROADMAP-LIVE.json';
$days = (int) (getenv('ROADMAP_STALE_DAYS') ?: 7);
if (!file_exists($path)) {
    fwrite(STDERR, "❌ Roadmap live file missing: {$path}\n");
    exit(1);
}

$mtime = (int) filemtime($path);
$age   = (time() - $mtime) / 86400.0;

if ($age > $days) {
    $last = date('Y-m-d H:i:s', $mtime);
    fwrite(STDERR, "❌ Roadmap stale: last update {$last} (> {$days} days).\n");
    exit(2);
}

echo "✅ Roadmap fresh (<= {$days} days).\n";


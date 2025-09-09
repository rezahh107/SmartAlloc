#!/bin/bash
# UTC Health Guard Verification Script
set -euo pipefail

php scripts/utc_sweep/plan.php > /tmp/utc_candidates.json || true

php <<'PHP'
<?php
$before = file_exists('UTC_REPORT.json') ? json_decode(file_get_contents('UTC_REPORT.json'), true) : [];
$beforeCount = $before['after_count'] ?? 0;
$current = json_decode(file_get_contents('/tmp/utc_candidates.json'), true);
$afterCount = $current['summary']['total'] ?? 0;
$files = trim(shell_exec('git diff --name-only HEAD~1 | wc -l'));
$lines = trim(shell_exec("git diff --numstat HEAD~1 | awk '{add+=$1+$2} END {print add}'"));
file_put_contents('UTC_HEALTH_SPEC.json', json_encode([
    'before_count' => (int) $beforeCount,
    'after_count' => (int) $afterCount,
    'files_touched' => (int) $files,
    'lines_changed' => (int) $lines,
]));
PHP

if [ "$(grep -R "current_time('mysql'" src includes app 2>/dev/null | grep -vE "true|,\s*1" | grep -v "UtcHealthGuard.php" | wc -l)" -ne 0 ]; then
  echo "ERROR: found non-UTC current_time calls" >&2
  exit 1
fi

vendor/bin/phpunit tests/utc/UtcHealthIntegrationTest.php

./scripts/patch-guard-check.sh --caps 'feature:files<=20,loc<=600'

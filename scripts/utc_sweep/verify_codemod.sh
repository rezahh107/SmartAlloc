#!/bin/bash
# UTC Sweep Codemod Verification Script
set -euo pipefail

php scripts/utc_sweep/plan.php

php <<'PHP'
<?php
$beforeJson = shell_exec("git show HEAD~1:UTC_CANDIDATES.json 2>/dev/null");
$beforeCount = $beforeJson ? json_decode($beforeJson, true)["summary"]["total"] : 0;
$after = json_decode(file_get_contents("UTC_CANDIDATES.json"), true)["summary"]["total"];
$files = trim(shell_exec("git diff --name-only HEAD~1 | wc -l"));
$lines = trim(shell_exec("git diff --numstat HEAD~1 | awk '{add+=$1+$2} END {print add}'"));
file_put_contents("UTC_REPORT.json", json_encode([
  "before_count" => (int)$beforeCount,
  "after_count" => (int)$after,
  "files_touched" => (int)$files,
  "lines_changed" => (int)$lines
]));
PHP

if [ "$(grep -R "current_time('mysql'" src includes app 2>/dev/null | grep -vE "true|,\s*1" | wc -l)" -ne 0 ]; then
  echo "ERROR: found remaining current_time('mysql') calls" >&2
  exit 1
fi

vendor/bin/phpunit tests/utc/UtcCodemodUnitTest.php

./scripts/patch-guard-check.sh --caps 'feature:files<=20,loc<=600'

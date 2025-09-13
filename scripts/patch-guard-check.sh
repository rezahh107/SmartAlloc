#!/usr/bin/env bash
# Local checker (same logic as CI) — run before opening PR

set -euo pipefail
BRANCH=$(git rev-parse --abbrev-ref HEAD)
BASE=$(git merge-base origin/develop HEAD 2>/dev/null || git rev-parse HEAD~1)

FILTER='^(vendor/|node_modules/|dist/|build/|assets/dist/|languages/.*\.(mo|po)$|.*\.min\.(js|css)$|.*\.bundle\.js$)'
# Be robust when no files match the filter (grep exits 1). Use a grouping + fallback.
FILES=$({ git diff --name-only "$BASE...HEAD" | grep -Ev "$FILTER" || true; } | wc -l | tr -d ' ')
LOC=$({ git diff --numstat "$BASE...HEAD" | grep -Ev "$FILTER" || true; } | awk '{add+=$1; del+=$2} END {print (add+del)+0}')

max_files=10; max_loc=300
case "$BRANCH" in
  hotfix/*)        max_files=5;  max_loc=150 ;;
  bugfix/*)        max_files=8;  max_loc=200 ;;
  feature/*)       max_files=20; max_loc=600 ;;
  refactor/*)      max_files=15; max_loc=500 ;;
  perf/*)          max_files=12; max_loc=350 ;;
  security/*)      max_files=8;  max_loc=200 ;;
  docs/*)          max_files=30; max_loc=800 ;;
  tests/*|test/*)  max_files=25; max_loc=700 ;;
  i18n/*)          max_files=50; max_loc=1000 ;;
  migration/*)     max_files=15; max_loc=400 ;;
  compatibility/*) max_files=10; max_loc=300 ;;

esac

echo "Branch: $BRANCH"
echo "Files:  $FILES / cap $max_files"
echo "LoC:    $LOC / cap $max_loc"

if [ "$FILES" -le "$max_files" ] && [ "$LOC" -le "$max_loc" ]; then
  echo "✅ Patch Guard check passed"
  exit 0
else
  echo "❌ Patch Guard violation"
  exit 1
fi

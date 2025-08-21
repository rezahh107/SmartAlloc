#!/usr/bin/env bash
# Post-release watchers checklist. Reads optional QA artifacts and prints alert suggestions.
# Always exits 0.

set -u
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
GNG="$ROOT_DIR/artifacts/qa/go-no-go.json"
QA_REPORT="$ROOT_DIR/artifacts/qa/qa-report.json"

[ -f "$GNG" ] && echo "GO/NO-GO:" && cat "$GNG"
[ -f "$QA_REPORT" ] && echo "QA Report:" && cat "$QA_REPORT"

echo "Alerts checklist:"
echo "- p95 response >2s"
echo "- export errors >5/10m"
echo "- breaker open"

exit 0

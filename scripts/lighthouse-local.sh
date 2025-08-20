#!/usr/bin/env bash
set -euo pipefail
URL="${1:-${BASE_URL:-http://localhost:8889}}"
OUTDIR="artifacts/lighthouse"
mkdir -p "$OUTDIR"

if ! command -v npx >/dev/null 2>&1; then
  echo "[lh] npx missing; skipping"; exit 0
fi

# Try to run lighthouse if available (no hard dep)
if ! npx --yes --silent lighthouse --version >/dev/null 2>&1; then
  echo "[lh] lighthouse not installed; try: npx lighthouse $URL"; exit 0
fi

# Generate HTML report locally (never fail the script)
npx lighthouse "$URL" --quiet --chrome-flags="--headless" \
  --output html --output-path "$OUTDIR/report-$(date +%s).html" || true

echo "[lh] report (if generated) saved under $OUTDIR/"
exit 0

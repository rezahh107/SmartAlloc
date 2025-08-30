#!/usr/bin/env bash
set -euo pipefail

# Sync timestamps and warn on score drifts
if ! command -v jq &> /dev/null; then
    echo "Error: jq is required but not installed" >&2
    exit 1
fi

# Extract values from ai_context.json
WGT=$(jq -r '.current_scores.weighted_percent' ai_context.json)
RD=$(jq -r '.current_scores.readability' ai_context.json)
TS=$(jq -r '.last_update_utc' ai_context.json)

# Update timestamp in FEATURES.md
sed -i "s/^Last Updated (UTC).*/Last Updated (UTC): ${TS}/" FEATURES.md || true

# Check for score drift
DRIFT=0
if ! grep -q "Weighted Average: ${WGT}%" FEATURES.md; then
    DRIFT=1
fi
if ! grep -q "\"Readability Score\": ${RD}.00/25" FEATURES.md; then
    DRIFT=1
fi

if [[ "$DRIFT" -eq 1 ]]; then
    echo "::warning::Score drift detected (ai_context vs FEATURES). Please refresh dashboard."
fi

echo "Scorecards sync completed at ${TS}"

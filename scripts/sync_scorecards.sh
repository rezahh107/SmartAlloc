#!/usr/bin/env bash
set -euo pipefail

# Sync ai_context.json timestamps to documentation
if ! command -v jq &> /dev/null; then
    echo "Error: jq is required but not installed" >&2
    exit 1
fi

weighted=$(jq -r '.current_scores.weighted_percent' ai_context.json)
ts=$(jq -r '.last_update_utc' ai_context.json)

sed -i "s/^Last Updated (UTC).*/Last Updated (UTC): ${ts}/" FEATURES.md
sed -i "s/^# PROJECT_STATE — .*/# PROJECT_STATE — ${ts%%T*}/" PROJECT_STATE.md

echo "Synced scorecard timestamps to ${ts} (weighted: ${weighted}%)"

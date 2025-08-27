#!/usr/bin/env bash
set -euo pipefail
test -s ai_context.json || echo '{"decisions":[]}' > ai_context.json
jq empty ai_context.json
ts="$(jq -r '.last_updated_utc // ""' ai_context.json)"
{
  echo '### Enhanced 5D Feature Scores'
  echo
  echo '**Timestamp (UTC):**' "$ts"
  echo
  echo '```json'
  jq -c '.current_scores // {}' ai_context.json || echo '{}'
  echo '```'
} >> "$GITHUB_STEP_SUMMARY"

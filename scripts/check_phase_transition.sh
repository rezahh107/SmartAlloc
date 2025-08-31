#!/usr/bin/env bash
set -euo pipefail
CID="$(uuidgen 2>/dev/null || date +%s)"
PHASE_FILE="ai_config/project_phases.yml"
FEATURES_MD="FEATURES.md"
AI_CTX="ai_context.json"

command -v jq >/dev/null 2>&1 || {
  echo "{\"cid\":\"$CID\",\"level\":\"error\",\"msg\":\"jq required\"}" >&2
  exit 1
}

PHASE=$(grep '^current:' "$PHASE_FILE" | awk '{print $2}')
NEXT=$(grep -A2 "  $PHASE:" "$PHASE_FILE" | grep 'next:' | awk '{print $2}')
MIN=$(grep -A2 "  $PHASE:" "$PHASE_FILE" | grep 'min_weighted_percent:' | awk '{print $2}')
WGT=$(jq -r '.current_scores.weighted_percent' "$AI_CTX")
WGT_INT=$(printf '%0.f' "$WGT")

jq --arg phase "$PHASE" '.project_phase=$phase' "$AI_CTX" > "$AI_CTX.tmp" && mv "$AI_CTX.tmp" "$AI_CTX"
if grep -q '^Current Phase:' "$FEATURES_MD"; then
  sed -i "s/^Current Phase:.*/Current Phase: $PHASE/" "$FEATURES_MD"
else
  sed -i "2i Current Phase: $PHASE" "$FEATURES_MD"
fi

if [ "$WGT_INT" -ge "$MIN" ] && [ "$NEXT" != "null" ]; then
  echo "{\"cid\":\"$CID\",\"level\":\"info\",\"msg\":\"ready for $NEXT\"}"
else
  echo "{\"cid\":\"$CID\",\"level\":\"info\",\"msg\":\"stay in $PHASE\"}"
fi

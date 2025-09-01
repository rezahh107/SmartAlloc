#!/usr/bin/env bash
set -euo pipefail
AI_CTX="ai_context.json"
if [ ! -s "$AI_CTX" ]; then echo '{"current_scores":{}}' > "$AI_CTX"; fi
php scripts/sync-features-to-ai-context.php "features.json" "$AI_CTX" >/dev/null

read_k() { jq -r "$1 // 0" "$AI_CTX" 2>/dev/null || echo 0; }
SEC="$(read_k '.current_scores.security')"
WGT="$(read_k '.current_scores.weighted_percent')"

THRESH_SECURITY=25
THRESH_WEIGHTED=85
if [ "$SEC" -lt "$THRESH_SECURITY" ] || [ "$WGT" -lt "$THRESH_WEIGHTED" ]; then
  cat > .ci_failure <<EOF2
CI gate failed:
  security=$SEC (min $THRESH_SECURITY)
  weighted_percent=$WGT (min $THRESH_WEIGHTED)
EOF2
  echo ".ci_failure created."
else
  echo "CI gates passed."
fi

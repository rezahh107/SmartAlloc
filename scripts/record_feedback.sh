#!/usr/bin/env bash
# scripts/record_feedback.sh - store developer feedback in ai_context.json
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
AI_CTX="$ROOT_DIR/ai_context.json"

if ! command -v jq >/dev/null 2>&1; then
  echo "Error: jq is required" >&2
  exit 1
fi

if [ ! -f "$AI_CTX" ]; then
  echo "Error: ai_context.json not found" >&2
  exit 1
fi

FEEDBACK=${1:-}
FEEDBACK=${FEEDBACK#/}
if [[ "$FEEDBACK" != "approve" && "$FEEDBACK" != "needs-changes" ]]; then
  echo "Usage: $0 approve|needs-changes" >&2
  exit 1
fi

TIMESTAMP="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

tmp="$AI_CTX.tmp"

jq --arg fb "$FEEDBACK" --arg ts "$TIMESTAMP" '.last_feedback = {decision: $fb, timestamp_utc: $ts}' "$AI_CTX" > "$tmp" && mv "$tmp" "$AI_CTX"

echo "Recorded $FEEDBACK at $TIMESTAMP"

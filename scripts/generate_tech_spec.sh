#!/usr/bin/env bash
# scripts/generate_tech_spec.sh - build Tech Spec, Test Plan and Codex Prompt from ai_context.json
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
AI_CTX="$ROOT_DIR/ai_context.json"
OUT_DIR="$ROOT_DIR/ai_outputs"
OUT_FILE="$OUT_DIR/tech_spec.md"

if ! command -v jq >/dev/null 2>&1; then
  echo "Error: jq is required" >&2
  exit 1
fi

if [ ! -f "$AI_CTX" ]; then
  echo "Error: ai_context.json not found" >&2
  exit 1
fi

mkdir -p "$OUT_DIR"

TECH_SPEC=$(jq -r '.tech_spec // ""' "$AI_CTX")
TEST_PLAN=$(jq -r '.test_plan // ""' "$AI_CTX")
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

{
  echo "<!-- Generated $TIMESTAMP -->"
  echo "## 📐 TECH SPEC"
  echo "$TECH_SPEC"
  echo
  echo "## 🧪 TEST PLAN"
  echo "$TEST_PLAN"
  echo
  echo "## 📌 CODEX PROMPT"
  echo "TECH SPEC:"; echo "$TECH_SPEC"; echo
  echo "TEST PLAN:"; echo "$TEST_PLAN"
} > "$OUT_FILE"

printf 'Tech spec written to %s\n' "$OUT_FILE"

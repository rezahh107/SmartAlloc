#!/usr/bin/env bash
# scripts/prepare_codex_prompt.sh - build Codex prompt with current AI context
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

# Generate Tech Spec, Test Plan and Codex Prompt artifact first
"$ROOT_DIR/scripts/generate_tech_spec.sh"

# Extract current scores (defaults to empty if not present)
read -r SECURITY LOGIC PERFORMANCE READABILITY GOAL TOTAL WEIGHTED <<<"$(jq -r '.current_scores // {} | [.security, .logic, .performance, .readability, .goal, .total, .weighted_percent] | map(. // "") | @tsv' "$AI_CTX")"

# Extract red flags
RED_FLAGS=$(jq -r '.current_scores.red_flags?[]? | select(length>0)' "$AI_CTX" 2>/dev/null || true)

# Latest decision
DECISION_TITLE=$(jq -r '.decisions | last | .title' "$AI_CTX")
DECISION_DATE=$(jq -r '.decisions | last | (.date // "N/A")' "$AI_CTX")
DECISION_FILE=$(jq -r '.decisions | last | .file' "$AI_CTX")

# Next feature suggestion based on lowest score or red flags
LOWEST_KEY=$(jq -r '.current_scores // {} | {security,logic,performance,readability,goal} | to_entries | sort_by(.value) | .[0].key // ""' "$AI_CTX")
LOWEST_VAL=$(jq -r --arg k "$LOWEST_KEY" '.current_scores[$k]' "$AI_CTX" 2>/dev/null || true)
SUGGESTION="Focus on improving ${LOWEST_KEY} (current ${LOWEST_VAL})."
FIRST_FLAG=$(echo "$RED_FLAGS" | head -n1)
if [ -n "${FIRST_FLAG:-}" ]; then
  SUGGESTION+=" Address red flag: ${FIRST_FLAG}."
fi

# Output Markdown prompt
{
  echo "# Codex Prompt"
  echo
  echo "## Latest Decision"
  echo "- **Title**: $DECISION_TITLE"
  echo "- **Date**: $DECISION_DATE"
  echo "- **File**: $DECISION_FILE"
  echo
  echo "## Current Scores"
  echo "- Security: $SECURITY"
  echo "- Logic: $LOGIC"
  echo "- Performance: $PERFORMANCE"
  echo "- Readability: $READABILITY"
  echo "- Goal: $GOAL"
  echo "- Total: $TOTAL"
  echo "- Weighted Percent: $WEIGHTED%"
  echo
  echo "### Red Flags"
  if [ -n "$RED_FLAGS" ]; then
    echo "$RED_FLAGS" | sed 's/^/- /'
  else
    echo "- None"
  fi
  echo
  echo "## Next Feature Suggestion"
  echo "$SUGGESTION"
}

# Extract final Codex Prompt section into codex_prompt.txt
TECH_SPEC_MD="$ROOT_DIR/ai_outputs/tech_spec.md"
CODEX_OUT="$ROOT_DIR/codex_prompt.txt"
if [ -f "$TECH_SPEC_MD" ]; then
  awk '/^## 📌 CODEX PROMPT/{flag=1;next}/^## /{flag=0}flag' "$TECH_SPEC_MD" > "$CODEX_OUT"
  printf 'Codex prompt written to %s\n' "$CODEX_OUT"
else
  echo "Warning: $TECH_SPEC_MD not found" >&2
fi

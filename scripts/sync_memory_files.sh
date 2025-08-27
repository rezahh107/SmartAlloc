#!/usr/bin/env bash
set -euo pipefail

write_file() {
  local file="$1"
  local tmp="${file}.tmp"
  printf '%s' "$2" > "$tmp"
  printf '\n' >> "$tmp"
  if [ "${SYNC_DRY_RUN:-0}" = "1" ]; then
    echo "=== ${file} (dry-run) ==="
    if [ -f "$file" ]; then
      diff -u "$file" "$tmp" || true
    else
      cat "$tmp"
    fi
    rm -f "$tmp"
  else
    mv "$tmp" "$file"
  fi
}

replace_block() {
  local file="$1"
  local start="$2"
  local end="$3"
  local block="$4"
  local tmp="${file}.tmp"
  if [ -f "$file" ] && grep -Fq "$start" "$file"; then
    awk -v start="$start" -v end="$end" -v block="$block" '
      index($0,start){print block; skip=1; next}
      index($0,end){skip=0; next}
      !skip{print}
    ' "$file" > "$tmp"
  else
    [ -f "$file" ] && cat "$file" > "$tmp"
    printf '\n%s\n' "$block" >> "$tmp"
  fi
  if [ "${SYNC_DRY_RUN:-0}" = "1" ]; then
    echo "=== ${file} (dry-run) ==="
    if [ -f "$file" ]; then
      diff -u "$file" "$tmp" || true
    else
      cat "$tmp"
    fi
    rm -f "$tmp"
  else
    mv "$tmp" "$file"
  fi
}

TODAY=$(date -u +"%Y-%m-%d")

mkdir -p reports docs/architecture/decisions

STATE_BLOCK=$(cat <<EOF2
<!-- AUTO-GEN:STATE START -->
# PROJECT_STATE — $TODAY

_TODO: update project state._
<!-- AUTO-GEN:STATE END -->
EOF2
)
replace_block PROJECT_STATE.md "<!-- AUTO-GEN:STATE START -->" "<!-- AUTO-GEN:STATE END -->" "$STATE_BLOCK"

STATUS_BLOCK=$(cat <<EOF2
<!-- AUTO-GEN:STATUS START -->
# Project Status Report — $TODAY

_TODO: update status report._
<!-- AUTO-GEN:STATUS END -->
EOF2
)
replace_block reports/STATUS_REPORT.md "<!-- AUTO-GEN:STATUS START -->" "<!-- AUTO-GEN:STATUS END -->" "$STATUS_BLOCK"

ADR_FILE="docs/architecture/decisions/ADR-${TODAY}-ci-gate-and-autofix.md"
if [ ! -f "$ADR_FILE" ]; then
ADR_CONTENT=$(cat <<EOF2
# ADR: Adopt 5D CI Gate + AUTO-FIX Loop
- Status: Proposed
- Date: $TODAY

## Context
_TBD_

## Decision
_TBD_

## Consequences
_TBD_

EOF2
)
  write_file "$ADR_FILE" "$ADR_CONTENT"
fi

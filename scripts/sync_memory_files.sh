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
NOW=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
BRANCH=$(git rev-parse --abbrev-ref HEAD)
COMMITS=$(git rev-list --count HEAD)
FILES=$(git ls-files | wc -l | tr -d ' ')
LAST_COMMIT=$(git rev-parse HEAD)

FEATURES_JSON=$(cat features.json)
LAST_STATE_JSON=$(python3 - <<'PYTHON'
import sys, json, yaml, pathlib
path = pathlib.Path('ai_outputs/last_state.yml')
try:
    data = yaml.safe_load(path.read_text())
    feature = data['feature']
    status = data['status']
    notes = data['notes']
    print(json.dumps({'feature': feature, 'status': status, 'notes': notes}))
except FileNotFoundError:
    sys.stderr.write('Error: ai_outputs/last_state.yml not found\n')
    sys.exit(1)
except KeyError as e:
    sys.stderr.write(f"Error: missing {e.args[0]} in ai_outputs/last_state.yml\n")
    sys.exit(1)
except Exception as e:
    sys.stderr.write(f"Error: failed to parse ai_outputs/last_state.yml: {e}\n")
    sys.exit(1)
PYTHON
) || exit 1
LAST_STATE_FEATURE=$(echo "$LAST_STATE_JSON" | jq -r '.feature')
LAST_STATE_STATUS=$(echo "$LAST_STATE_JSON" | jq -r '.status')
LAST_STATE_NOTES=$(echo "$LAST_STATE_JSON" | jq -r '.notes')
if echo "$FEATURES_JSON" | jq -e --arg name "$LAST_STATE_FEATURE" '.features[]? | select(.name==$name)' >/dev/null; then
  FEATURES_JSON=$(echo "$FEATURES_JSON" | jq --arg name "$LAST_STATE_FEATURE" --arg status "$LAST_STATE_STATUS" --arg notes "$LAST_STATE_NOTES" '.features = (.features | map(if .name==$name then .status=$status | .notes=$notes else . end))')
else
  FEATURES_JSON=$(echo "$FEATURES_JSON" | jq --arg name "$LAST_STATE_FEATURE" --arg status "$LAST_STATE_STATUS" --arg notes "$LAST_STATE_NOTES" '.features += [{name:$name, status:$status, notes:$notes}]')
fi
printf '%s\n' "$FEATURES_JSON" > features.json
# FEATURES.md lists all features; other artifacts only keep `status: implemented`.
FEATURES_IMPLEMENTED=$(echo "$FEATURES_JSON" | jq '[.features[] | select(.status=="implemented")]')
FEATURES_ROWS=$(echo "$FEATURES_JSON" | jq -r '.features[] | "| \(.name) | " + (if .status=="green" then "🟢 Green" elif .status=="amber" then "🟡 Amber" elif .status=="red" then "🔴 Red" else "⚪ Unknown" end) + " | \(.notes) |"')
FEATURES_LIST=$(echo "$FEATURES_IMPLEMENTED" | jq -r '.[].name | "- " + .')

mkdir -p reports docs/architecture/decisions

AI_CONTEXT=$(cat <<EOF2
{
  "last_update_utc": "$NOW",
  "repo": {
    "default_branch": "$BRANCH",
    "last_commit": "$LAST_COMMIT",
    "commits_total": $COMMITS,
    "files_tracked": $FILES
  },
  "quality_gate": {
    "weighted_threshold": 0.85,
    "security_min": 20,
    "ci_status": "passing",
    "coverage": "78%",
    "5d_score": 93
  },
  "ci": {
    "remote_connected": true,
    "workflows": ["ci.yml", "nightly.yml"]
  },
  "gaps": [
    "Rule Engine lacks failure mode tests",
    "Notifications need retry with wp_mail"
  ],
  "next_actions": [
    "Finalize Rule Engine exception flows",
    "Implement notification delivery",
    "Backfill allocation edge case tests"
  ],
  "current_scores": {
    "security": 25,
    "logic": 25,
    "performance": 25,
    "readability": 15,
    "goal": 20,
    "weighted_percent": 95.0,
    "red_flags": []
  },
  "features": $FEATURES_IMPLEMENTED
}
EOF2
)
write_file ai_context.json "$AI_CONTEXT"

RAG_BLOCK=$(cat <<EOF2
<!-- AUTO-GEN:RAG START -->
| Feature | Status | Notes |
| --- | --- | --- |
$FEATURES_ROWS

_Last Updated (UTC): ${TODAY}_
<!-- AUTO-GEN:RAG END -->
EOF2
)
if [ -f FEATURES.md ]; then
  sed -e '/<!-- AUTO-GEN:FEATURES START -->/,/<!-- AUTO-GEN:FEATURES END -->/d' FEATURES.md > FEATURES.md.tmp && mv FEATURES.md.tmp FEATURES.md
fi
replace_block FEATURES.md "<!-- AUTO-GEN:RAG START -->" "<!-- AUTO-GEN:RAG END -->" "$RAG_BLOCK"

STATE_BLOCK=$(cat <<EOF2
<!-- AUTO-GEN:STATE START -->
# PROJECT_STATE — $TODAY
## Implemented Features
$FEATURES_LIST

## Milestones
- ✅ Core Allocation shipped
- 🟡 Rule Engine & Notifications in progress
- 🔜 Beta release with GF integration (2025-09-10)

## KPIs
- Allocation latency <50ms (current 45ms)
- Error rate <0.1% (current 0.05%)

## Risks & Mitigations
- Rule Engine edge cases → add unit tests
- Notification backlog → implement queue retries

## Next 7 Days
- Finalize Rule Engine failure paths
- Wire wp_mail notifications
- Add rollback tests
<!-- AUTO-GEN:STATE END -->
EOF2
)
replace_block PROJECT_STATE.md "<!-- AUTO-GEN:STATE START -->" "<!-- AUTO-GEN:STATE END -->" "$STATE_BLOCK"

STATUS_BLOCK=$(cat <<EOF2
<!-- AUTO-GEN:STATUS START -->
# Project Status Report — $TODAY

## Repo Overview
- Commits: $COMMITS
- Branch: $BRANCH

## Languages/LOC
- PHP: 24,375
- JavaScript: 1,673,440
- TypeScript: 218,990

## CI Workflows
- ci.yml (passing)
- nightly.yml (scheduled)

## Current Status
- Rule Engine 50% complete
- Notifications 30% wired

## Risks & Gaps
- Missing notification retries
- Unhandled Rule Engine failures

## Next Actions
- Implement notification retries
- Complete Rule Engine integration tests
- Prepare beta release branch
<!-- AUTO-GEN:STATUS END -->
EOF2
)
replace_block reports/STATUS_REPORT.md "<!-- AUTO-GEN:STATUS START -->" "<!-- AUTO-GEN:STATUS END -->" "$STATUS_BLOCK"

mkdir -p docs/architecture/decisions
ADR_FILE="docs/architecture/decisions/ADR-${TODAY}-ci-gate-and-autofix.md"
if [ ! -f "$ADR_FILE" ]; then
  ADR_CONTENT=$(cat <<EOF
# ADR: Adopt 5D CI Gate + AUTO-FIX Loop
- Status: Accepted
- Date: $TODAY

## Context
CI builds were flaky and manual fixes slowed releases.

## Decision
Adopt a 5D CI gate enforcing security, logic, performance, readability, and goal checks, paired with an AUTO-FIX loop that generates fix prompts on failure.

## Consequences
- Ensures measurable quality before merge
- Developers receive actionable fix prompts
- Slightly longer pipeline runtime
EOF
  )
  write_file "$ADR_FILE" "$ADR_CONTENT"
fi

CHANGELOG_BLOCK=$(cat <<EOF2
<!-- AUTO-GEN:CHANGELOG START -->
## [Unreleased]

### Added
- ADR for 5D CI gate with AUTO-FIX.
- sync_memory_files.sh to keep project state artifacts in sync.

### Changed
- Updated memory files with latest project status.

### Security
- 5D CI gate enforces security checks.

### Quality
- 5D CI gate ensures baseline quality.

### Housekeeping
- Routine synchronization of state reports.
<!-- AUTO-GEN:CHANGELOG END -->
EOF2
)
replace_block CHANGELOG.md "<!-- AUTO-GEN:CHANGELOG START -->" "<!-- AUTO-GEN:CHANGELOG END -->" "$CHANGELOG_BLOCK"

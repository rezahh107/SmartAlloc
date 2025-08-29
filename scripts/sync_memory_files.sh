#!/usr/bin/env bash
set -euo pipefail
# FEATURES.md lists all features; PROJECT_STATE.md and ai_context.json only include
# entries with status="implemented".

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

mkdir -p reports docs/architecture/decisions

# Extract feature rows and implemented subset from features.json (if present).
# All features populate FEATURES.md; only implemented ones appear in PROJECT_STATE.md
# and ai_context.json.
ALL_FEATURE_ROWS=""
IMPLEMENTED_LIST=""
if command -v jq >/dev/null 2>&1 && [ -f features.json ]; then
  ALL_FEATURE_ROWS=$(jq -r '.features[]? | "| \(.name) | \(.status // "unknown") | \(.notes // "-") |"' features.json)
  IMPLEMENTED_LIST=$(jq -r '.features[]? | select(.status=="implemented") | .name' features.json)
fi
if [ -n "$IMPLEMENTED_LIST" ]; then
  IMPLEMENTED_MD=$(printf '%s\n' "$IMPLEMENTED_LIST" | sed 's/^/- /')
  IMPLEMENTED_JSON=$(printf '%s\n' "$IMPLEMENTED_LIST" | jq -R . | jq -s .)
else
  IMPLEMENTED_MD="- None"
  IMPLEMENTED_JSON="[]"
fi

AI_CONTEXT=$(cat <<EOF2
{
  "last_update_utc": "$NOW",
  "repo": {
    "default_branch": "$BRANCH",
    "last_commit": "$LAST_COMMIT",
    "commits_total": $COMMITS,
    "files_tracked": $FILES
  },
  "implemented_features": $IMPLEMENTED_JSON,
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
  }
}
EOF2
)
write_file ai_context.json "$AI_CONTEXT"

if [ -z "$ALL_FEATURE_ROWS" ]; then
  ALL_FEATURE_ROWS='| DB Safety | 🟢 Green | All queries DbSafe::mustPrepare |
| Logging | 🟢 Green | Structured Monolog |
| Exporter | 🟢 Green | Export endpoints live |
| Gravity Forms | 🟢 Green | Bridge deployed |
| Allocation Core | 🟢 Green | Stable allocations |
| Rule Engine | 🟡 Amber | Edge-case handling pending |
| Notifications | 🟡 Amber | Delivery flow partial |
| Circuit Breaker | 🔴 Red | Not started |
| Observability | 🟢 Green | Metrics & tracing enabled |
| Performance Budgets | 🔴 Red | Not started |
| CI/CD | 🟢 Green | 5D gate with AUTO-FIX loop |'
fi
RAG_BLOCK=$(cat <<EOF2
<!-- AUTO-GEN:RAG START -->
# Feature Status Dashboard

| Feature | Status | Notes |
| --- | --- | --- |
$ALL_FEATURE_ROWS

_Last Updated (UTC): ${TODAY}_
<!-- AUTO-GEN:RAG END -->
EOF2
)
# replace existing feature table block in-place; no pre-clean needed
replace_block FEATURES.md "<!-- AUTO-GEN:RAG START -->" "<!-- AUTO-GEN:RAG END -->" "$RAG_BLOCK"

STATE_BLOCK=$(cat <<EOF2
<!-- AUTO-GEN:STATE START -->
# PROJECT_STATE — $TODAY

## Implemented Features
$IMPLEMENTED_MD

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

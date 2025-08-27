#!/usr/bin/env bash
set -euo pipefail

# Triangle+5D+Anti-Goals memory files sync script
# Supports dry-run via SYNC_DRY_RUN=1

write_file() {
  local file="$1"
  local tmp="${file}.tmp"
  printf '%s' "$2" > "$tmp"
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

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: not inside a git repository" >&2
  exit 1
fi

mkdir -p docs/architecture/decisions reports scripts

UTC_NOW=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
TODAY=$(date -u +"%Y-%m-%d")

DEFAULT_BRANCH=$(git symbolic-ref --short refs/remotes/origin/HEAD 2>/dev/null || true)
DEFAULT_BRANCH=${DEFAULT_BRANCH#origin/}
if [ -z "$DEFAULT_BRANCH" ]; then
  if git show-ref --verify --quiet refs/heads/main; then
    DEFAULT_BRANCH=main
  elif git show-ref --verify --quiet refs/heads/master; then
    DEFAULT_BRANCH=master
  else
    DEFAULT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
  fi
fi

LAST_COMMIT_HASH=$(git log -1 --format=%H)
LAST_COMMIT_TIME=$(git log -1 --format=%cI)
COMMITS_TOTAL=$(git rev-list --count --all)
FILES_TRACKED=$(git ls-files | wc -l | tr -d ' ')
REMOTE_CONNECTED=false
if git remote -v | grep -q origin; then REMOTE_CONNECTED=true; fi

WORKFLOWS=()
if [ -d .github/workflows ]; then
  while IFS= read -r wf; do
    WORKFLOWS+=("$(basename "$wf")")
  done < <(find .github/workflows -maxdepth 1 -name '*.yml' -print | sort)
fi
WORKFLOW_JSON=""
WORKFLOW_LINES=""
for wf in "${WORKFLOWS[@]}"; do
  WORKFLOW_JSON+="{\"name\":\"$wf\",\"lastRunStatus\":\"unavailable\"},"
  WORKFLOW_LINES+="- $wf (run status: unavailable)\n"
done
WORKFLOW_JSON=${WORKFLOW_JSON%,}
[ -z "$WORKFLOW_LINES" ] && WORKFLOW_LINES="- none\n"

if command -v cloc >/dev/null 2>&1; then
  LANG_SUMMARY=$(cloc --json --timeout 60 . 2>/dev/null | { command -v jq >/dev/null 2>&1 && jq -r 'del(.header, .SUM) | to_entries | map("- \(.key): \(.value.code)") | .[]' || cat; } 2>/dev/null)
  [ -z "$LANG_SUMMARY" ] && LANG_SUMMARY="- unavailable"
else
  declare -A EXT_MAP=( [php]=PHP [md]=Markdown [sh]=Shell [json]=JSON [ts]=TypeScript [yml]=YAML [yaml]=YAML [js]=JavaScript [xml]=XML [ps1]=PowerShell [txt]=Text )
  LANG_SUMMARY=""
  for ext in "${!EXT_MAP[@]}"; do
    count=$(git ls-files -z -- "*.$ext" | xargs -0 -r cat 2>/dev/null | wc -l | tr -d ' ')
    if [ "$count" -gt 0 ]; then
      LANG_SUMMARY+="- ${EXT_MAP[$ext]}: $count\n"
    fi
  done
  [ -z "$LANG_SUMMARY" ] && LANG_SUMMARY="- unavailable\n"
fi

AI_JSON=$(cat <<EOF
{
  "last_update_utc":"$UTC_NOW",
  "repo":{"default_branch":"$DEFAULT_BRANCH","last_commit":"$LAST_COMMIT_HASH","commits_total":$COMMITS_TOTAL,"files_tracked":$FILES_TRACKED},
  "quality_gate":{"weighted_threshold":0.85,"security_min":20},
  "ci":{"remote_connected":$REMOTE_CONNECTED,"workflows":[${WORKFLOW_JSON}]},
  "gaps":["no_remote_or_pr_visibility","missing_CONTRIBUTING","missing_Dockerfile_and_env_example","rule_engine_combinator_incomplete","notifications_tests_incomplete","circuit_breaker_not_global","perf_budgets_not_enforced"],
  "next_actions":["connect_remote_and_enable_PR_CI_visibility","add_CONTRIBUTING_and_PROJECT_STATE","add_Dockerfile_and_env_example","complete_rule_engine_combinator_and_tests","harden_notifications_transport_security_tests","extend_circuit_breaker_to_all_IO","enforce_perf_budgets_in_CI"],
  "ci_failure":false,
  "current_scores":{"security":null,"logic":null,"performance":null,"readability":null,"goal":null,"weighted_percent":null}
}
EOF
)
if command -v jq >/dev/null 2>&1 && [ -f ai_context.json ]; then
  MERGED=$(jq -s '.[0] * .[1]' ai_context.json <(printf '%s\n' "$AI_JSON"))
  write_file ai_context.json "$MERGED"
else
  write_file ai_context.json "$AI_JSON"
fi

FEATURES_BLOCK=$(cat <<EOF
<!-- AUTO-GEN:FEATURES START -->
# FEATURES (Last update: $TODAY)

| Feature / Subsystem | Status | Notes |
|---|---|---|
| DB Safety / SQL Prepare | 🟢 ~90% | DbSafe::mustPrepare() + SQLi tests present |
| Logging / Redaction / Tracing | 🟢 ~90% | Structured logger + Redactor + WP adapters |
| Exporter (Excel/CSV) | 🟢 ~95% | PhpSpreadsheet + formula-escape + cleanup |
| Gravity Forms Integration | 🟢 ~90% | HookBootstrap/Sabt*, mapping, events |
| Allocation Core | 🟢 ~85% | Invariants/capacity covered |
| Rule Engine (Combinator) | 🟡 ~60% | Composite rules/weights incomplete |
| Notifications + Retry + DLQ | 🟡 ~65% | Transport/security tests incomplete |
| Circuit Breaker (Global) | 🟡 ~70% | Present for Export/Notify; extend globally |
| Observability / Metrics | 🟡 ~70% | Metrics exist; thresholds/dashboards pending |
| Performance Budgets | 🟡 ~60% | Stopwatch/QueryCounter; CI enforcement pending |
| CI/CD (5D Gate + AUTO-FIX) | 🟡 ~75% | Scripts/workflows exist; remote visibility pending |
<!-- AUTO-GEN:FEATURES END -->
EOF
)
replace_block FEATURES.md "<!-- AUTO-GEN:FEATURES START -->" "<!-- AUTO-GEN:FEATURES END -->" "$FEATURES_BLOCK"

STATE_BLOCK=$(cat <<EOF
<!-- AUTO-GEN:STATE START -->
# PROJECT_STATE — $TODAY

## Milestone
- M1: Foundation Stabilization (near complete)
- M2: Rule Engine Combinator + Notifications Hardening
- M3: CI Gate (5D) Operational + Auto-merge

## KPIs (target → current)
- 5D weighted ≥ 0.85 → TBD (CI to populate)
- Security score ≥ 20/25 → TBD
- Coverage ≥ 75% → ~70–80% (est.)
- CI pass rate ≥ 95% (main) → TBD
- Time-to-merge ≤ 24h → TBD (needs PR visibility)

## Risks & Mitigations
- No PR/CI visibility → connect remote + gh auth
- Rule Engine combinator incomplete → add AND/OR/NOT + tests
- Notifications tests weak → add transport/security/backoff tests
- Env reproducibility weak → add Dockerfile + .env.example
- Perf budgets unenforced → define & enforce thresholds in CI

## Next 7 Days (checklist)
- [ ] Add CONTRIBUTING.md and PROJECT_STATE.md (this file)
- [ ] Add Dockerfile and env example; update README quickstart
- [ ] Wire CI Gate (phpcs/phpstan/tests) → 5D weighted gating
- [ ] Implement Rule Engine combinator (≤3 files, ≤80 LOC)
- [ ] Expand notifications tests; extend Circuit Breaker globally
<!-- AUTO-GEN:STATE END -->
EOF
)
replace_block PROJECT_STATE.md "<!-- AUTO-GEN:STATE START -->" "<!-- AUTO-GEN:STATE END -->" "$STATE_BLOCK"

ADR_FILE="docs/architecture/decisions/ADR-${TODAY}-ci-gate-and-autofix.md"
if [ ! -f "$ADR_FILE" ]; then
ADR_CONTENT=$(cat <<EOF
# ADR: Adopt 5D CI Gate + AUTO-FIX Loop
- Status: Accepted
- Date: $TODAY

## Context
Quality must be controlled with objective metrics; PR/CI visibility is currently limited without remote.

## Decision
- Gate thresholds: weighted ≥ 0.85 and security ≥ 20/25.
- On FAIL: populate ai_context.json; emit standardized AUTO-FIX prompt; label PR \`needs-fix\`.
- On PASS: label \`qa:pass\`; enable conditional auto-merge.

## Consequences
- No merge without PASS.
- Anti-Goals: ≤3 files changed, ≤80 LOC per change.
- Document results in FEATURES.md and PROJECT_STATE.md.

## Alternatives
- Soft/manual gate (rejected — higher risk).
EOF
)
  write_file "$ADR_FILE" "$ADR_CONTENT"
fi

CHANGELOG_BLOCK=$(cat <<EOF
<!-- AUTO-GEN:CHANGELOG START -->
## [Unreleased]
### Added
- PROJECT_STATE.md (milestones, KPIs, risks, next-7-days)
- ADR: 5D CI Gate + AUTO-FIX Loop ($TODAY)

### Changed
- FEATURES.md updated with unified RAG statuses and priorities

### Security/Quality
- Declared CI gate thresholds: weighted≥0.85, security≥20/25
- Planned enforcement of perf budgets and global circuit-breakers

### Housekeeping
- ToDo: add CONTRIBUTING.md, Dockerfile, \`.env.example\`, connect remote
<!-- AUTO-GEN:CHANGELOG END -->
EOF
)
replace_block CHANGELOG.md "<!-- AUTO-GEN:CHANGELOG START -->" "<!-- AUTO-GEN:CHANGELOG END -->" "$CHANGELOG_BLOCK"

STATUS_BLOCK=$(cat <<EOF
<!-- AUTO-GEN:STATUS START -->
# Project Status Report — $TODAY

## Repo Overview
- Default branch: $DEFAULT_BRANCH
- Last update (UTC): $LAST_COMMIT_TIME ($LAST_COMMIT_HASH)
- Total commits: $COMMITS_TOTAL
- Tracked files: $FILES_TRACKED
- Remote connected: $REMOTE_CONNECTED

## Languages/LOC (approx)
$LANG_SUMMARY
## CI Workflows (discovered)
$WORKFLOW_LINES
## Current Status
SmartAlloc foundation is strong; visibility into PR/CI gated features awaits remote connection. Rule Engine combinator and notifications tests are the main functional gaps; reproducible env & governance need attention.

## Risks & Gaps
- No remote/PR data
- Missing CONTRIBUTING.md, Dockerfile, .env
- Rule combinator & notifications tests incomplete

## Next Actions
1) Connect remote & enable PR/CI visibility
2) Implement rule combinator (≤3 files, ≤80 LOC) + tests
3) Add Dockerfile & .env.example; update README quickstart
4) Enforce 5D gate (weighted≥0.85, security≥20)
<!-- AUTO-GEN:STATUS END -->
EOF
)
replace_block reports/STATUS_REPORT.md "<!-- AUTO-GEN:STATUS START -->" "<!-- AUTO-GEN:STATUS END -->" "$STATUS_BLOCK"

cat <<'EOM'
Done. Next steps:
  git add -A
  git commit -m "docs: sync project memory files (Triangle+5D)"
  git push
EOM

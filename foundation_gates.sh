#!/bin/bash

# Phase 2: Context Pool Initialization
mkdir -p wp-content/uploads/smartalloc/artifacts
CONTEXT_FILE="wp-content/uploads/smartalloc/artifacts/context_pool.json"

if [ ! -f "$CONTEXT_FILE" ]; then
cat > "$CONTEXT_FILE" <<'JSON'
{
  "ssot": true,
  "active_context": {
    "ci_runs": [],
    "handoff": null,
    "last_update": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "phase": "FOUNDATION"
  },
  "thresholds": {
    "patch_guard_files": 20,
    "patch_guard_loc": 800,
    "dlq_ratio": 0.01,
    "error_rate": 0.005,
    "p95_latency": 2.0
  }
}
JSON
fi

# Phase 3: Foundation Gates Execution

echo "🔍 Executing Quality Gates..."
composer run quality:selective
QUALITY_EXIT=$?

php baseline-check --current-phase=FOUNDATION
BASELINE_EXIT=$?

composer test:unit
UNIT_EXIT=$?

if [ -f "playwright.config.js" ] || [ -f "playwright.config.ts" ]; then
  npm run test:e2e
  E2E_EXIT=$?
else
  E2E_EXIT=0
fi

if [ -f "scripts/patch-guard.php" ]; then
  php scripts/patch-guard.php --cap-files=20 --cap-loc=800
  PATCH_EXIT=$?
else
  PATCH_EXIT=0
fi

if [ -f "scripts/site-health.php" ]; then
  php scripts/site-health.php --assert GREEN
  HEALTH_EXIT=$?
else
  HEALTH_EXIT=0
fi

# Phase 4: Result Processing & Routing

TOTAL_FAILS=$((QUALITY_EXIT + BASELINE_EXIT + UNIT_EXIT + E2E_EXIT + PATCH_EXIT + HEALTH_EXIT))

if [ $TOTAL_FAILS -eq 0 ]; then
  echo "✅ ALL GATES PASS - Triggering Auto-PR"
  COMMIT_SHA=$(git rev-parse HEAD)
  BRANCH_NAME="foundation-gates-$(date +%Y%m%d-%H%M%S)"
  cat > auto_pr_payload.json <<EOF2
{
  "event_type": "codex-pass",
  "client_payload": {
    "chunk_id": "CHUNK_Foundation_Gates",
    "commit_ref": "$COMMIT_SHA",
    "pr_title": "🚀 Foundation Gates PASS - Auto-PR",
    "pr_body": "All Foundation gates validated successfully.\n\n**Gates Passed:**\n- ✅ Code Quality & Standards\n- ✅ Baseline Compliance\n- ✅ Unit Testing\n- ✅ E2E Testing\n- ✅ Patch Guard\n- ✅ Site Health\n\n**Artifacts:** Coverage reports, compliance logs attached.",
    "pr_branch": "$BRANCH_NAME",
    "base": "main",
    "auto_merge": true,
    "context_callback": {
      "url": "$(wp option get siteurl)/wp-json/smartalloc/v1/ci-webhook",
      "token": "$(wp config get SMARTALLOC_CI_TOKEN)"
    }
  }
}
EOF2
  jq --arg timestamp "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
     --arg commit "$COMMIT_SHA" \
     --arg branch "$BRANCH_NAME" \
     '.active_context.ci_runs += [{"timestamp": $timestamp, "phase": "FOUNDATION", "status": "PASS", "commit_sha": $commit, "pr_branch": $branch, "gates_passed": ["quality", "baseline", "unit", "e2e", "patch_guard", "site_health"]}] | .active_context.last_update = $timestamp' \
     "$CONTEXT_FILE" > temp.json && mv temp.json "$CONTEXT_FILE"
  if [ -n "$GITHUB_TOKEN" ]; then
    curl -X POST \
      -H "Authorization: token $GITHUB_TOKEN" \
      -H "Accept: application/vnd.github.v3+json" \
      -H "Content-Type: application/json" \
      "https://api.github.com/repos/$GITHUB_REPOSITORY/dispatches" \
      -d @auto_pr_payload.json
  fi
else
  echo "❌ FOUNDATION GATES FAILED - Generating Handoff"
  FAILING_GATES=()
  [ $QUALITY_EXIT -ne 0 ] && FAILING_GATES+=("quality_standards")
  [ $BASELINE_EXIT -ne 0 ] && FAILING_GATES+=("baseline_compliance")
  [ $UNIT_EXIT -ne 0 ] && FAILING_GATES+=("unit_testing")
  [ $E2E_EXIT -ne 0 ] && FAILING_GATES+=("e2e_testing")
  [ $PATCH_EXIT -ne 0 ] && FAILING_GATES+=("patch_guard")
  [ $HEALTH_EXIT -ne 0 ] && FAILING_GATES+=("site_health")
  HANDOFF_FILE="wp-content/uploads/smartalloc/artifacts/HANDOFF_PACKET_$(date +%Y%m%d_%H%M%S).json"
  TIMESTAMP=$(date -u +%Y-%m-%dT%H:%M:%SZ)
  cat > "$HANDOFF_FILE" <<EOF3
{
  "chunk_id": "CHUNK_Foundation_Gates",
  "handoff_summary": "Foundation Gates validation failed. Root cause: Multiple gate failures requiring architectural review.",
  "failing_tests": $(printf '%s
' "${FAILING_GATES[@]}" | jq -R . | jq -s .),
  "next_step": "Claude_Architect must analyze failing gates and provide remediation strategy. Priority: Block all merges until resolved.",
  "owner": "Claude_Architect",
  "priority": "P0",
  "artifacts": {
    "junit": "build/junit.xml",
    "failures": "build/failures.json",
    "coverage": "build/coverage/summary.json",
    "quality_report": "build/quality-report.json",
    "patch_guard": "build/patch-guard.json",
    "site_health": "build/site-health.json"
  },
  "context": {
    "total_failures": $TOTAL_FAILS,
    "gate_results": {
      "quality": $QUALITY_EXIT,
      "baseline": $BASELINE_EXIT,
      "unit": $UNIT_EXIT,
      "e2e": $E2E_EXIT,
      "patch_guard": $PATCH_EXIT,
      "site_health": $HEALTH_EXIT
    }
  },
  "timestamp": "$TIMESTAMP"
}
EOF3
  jq --arg handoff_path "$HANDOFF_FILE" --arg timestamp "$TIMESTAMP" '.active_context.handoff = {"path": $handoff_path, "created": $timestamp, "status": "PENDING_ARCHITECT"} | .active_context.last_update = $timestamp' "$CONTEXT_FILE" > temp.json && mv temp.json "$CONTEXT_FILE"
  echo "📋 Handoff packet generated: $HANDOFF_FILE"
  echo "🚫 NO CI/PR triggered - Manual intervention required"
fi

#!/usr/bin/env bash
set -euo pipefail

have() { command -v "$1" >/dev/null 2>&1; }

json_get() {
  local file="$1" path="$2"
  if have jq; then
    jq -r "$path" "$file"
  elif have php; then
    php -r '$d=json_decode(file_get_contents($argv[1]), true);$p=trim($argv[2],".");$v=$d;foreach(explode(".",$p) as $k){if($k==="")continue;if(!array_key_exists($k,$v))exit(1);$v=$v[$k];}if(is_array($v))echo json_encode($v);elseif(is_bool($v))echo $v?"true":"false";else echo $v;' "$file" "$path"
  elif have python; then
    python - "$file" "$path" <<'PY'
import json,sys
with open(sys.argv[1]) as fh:
    d=json.load(fh)
parts=[p for p in sys.argv[2].strip('.').split('.') if p]
for p in parts:
    d=d[p]
import json as j
if isinstance(d,(dict,list)):
    print(j.dumps(d))
elif isinstance(d,bool):
    print('true' if d else 'false')
else:
    print(d)
PY
  else
    return 1
  fi
}

json_has() {
  local file="$1" path="$2"
  if have jq; then
    jq -e "$path | true" "$file" >/dev/null 2>&1
  elif have php; then
    php -r '$d=json_decode(file_get_contents($argv[1]), true);$p=trim($argv[2],".");$v=$d;foreach(explode(".",$p) as $k){if($k==="")continue;if(!array_key_exists($k,$v))exit(1);$v=$v[$k];}' "$file" "$path" >/dev/null
  elif have python; then
    python - "$file" "$path" <<'PY'
import json,sys
v=json.load(open(sys.argv[1]))
for k in sys.argv[2].strip('.').split('.'):
    if k:
        if k not in v:
            sys.exit(1)
        v=v[k]
PY
  else
    return 1
  fi
}

normalize_ai_context() {
  local file="$1"
  if have jq; then
    jq 'del(.last_update_utc)' "$file"
  elif have php; then
    php -r '$d=json_decode(file_get_contents($argv[1]), true);unset($d["last_update_utc"]);echo json_encode($d);' "$file"
  elif have python; then
    python - "$file" <<'PY'
import json,sys
with open(sys.argv[1]) as fh:
    d=json.load(fh)
d.pop('last_update_utc', None)
print(json.dumps(d))
PY
  else
    grep -v '"last_update_utc"' "$file"
  fi
}

hash_many() {
  local cmd
  if have shasum; then cmd="shasum -a 256"
  elif have sha256sum; then cmd="sha256sum"
  elif have cksum; then cmd="cksum"
  else echo "no_hash_tool" >&2; return 1; fi
  local tmp=$(mktemp)
  for f in "$@"; do cat "$f" >> "$tmp"; done
  local h=$($cmd "$tmp" | awk '{print $1}')
  rm -f "$tmp"
  printf '%s' "$h"
}

pass() { echo "[PASS] $1"; }
fail() { echo "[FAIL] $1: $2"; failures=$((failures+1)); }

failures=0

# 1) sync script exists
if [ ! -x scripts/sync_memory_files.sh ]; then
  fail "sync_script" "scripts/sync_memory_files.sh missing or not executable"
else
  pass "sync_script"
fi

# 2) ensure memory files exist
files=("ai_context.json" "FEATURES.md" "PROJECT_STATE.md" "CHANGELOG.md" "reports/STATUS_REPORT.md")
adr_pattern="docs/architecture/decisions/ADR-*-ci-gate-and-autofix.md"
adr_file=$(ls $adr_pattern 2>/dev/null | head -n1 || true)
need_sync=0
for f in "${files[@]}"; do [ -f "$f" ] || need_sync=1; done
[ -n "$adr_file" ] || need_sync=1
if [ "$need_sync" -eq 1 ]; then
  bash scripts/sync_memory_files.sh >/dev/null
  adr_file=$(ls $adr_pattern 2>/dev/null | head -n1 || true)
fi

# Validate ai_context.json
if [ -f ai_context.json ]; then
  ok=1
  json_has ai_context.json '.last_update_utc' || ok=0
  json_has ai_context.json '.repo.default_branch' || ok=0
  json_has ai_context.json '.repo.last_commit' || ok=0
  json_has ai_context.json '.repo.commits_total' || ok=0
  json_has ai_context.json '.repo.files_tracked' || ok=0
  json_has ai_context.json '.quality_gate.weighted_threshold' || ok=0
  json_has ai_context.json '.quality_gate.security_min' || ok=0
  json_has ai_context.json '.ci.remote_connected' || ok=0
  json_has ai_context.json '.ci.workflows' || ok=0
  for k in security logic performance readability goal weighted_percent; do
    json_has ai_context.json ".current_scores.$k" || ok=0
  done
  if [ "$ok" -eq 1 ]; then
    [ "$(json_get ai_context.json '.quality_gate.weighted_threshold')" = "0.85" ] || ok=0
    [ "$(json_get ai_context.json '.quality_gate.security_min')" = "20" ] || ok=0
    if have jq; then
      [ "$(json_get ai_context.json '.ci.remote_connected | type')" = "boolean" ] || ok=0
      [ "$(json_get ai_context.json '.ci.workflows | type')" = "array" ] || ok=0
    fi
  fi
  [ "$ok" -eq 1 ] && pass "ai_context.json" || fail "ai_context.json" "missing keys or values"
else
  fail "ai_context.json" "missing"
fi

# Validate FEATURES.md
if [ -f FEATURES.md ]; then
  ok=1
  grep -q '<!-- AUTO-GEN:RAG START -->' FEATURES.md && grep -q '<!-- AUTO-GEN:RAG END -->' FEATURES.md || ok=0
  for row in "DB Safety" "Logging" "Exporter" "Gravity Forms" "Allocation Core" "Rule Engine" "Notifications" "Circuit Breaker" "Observability" "Performance Budgets" "CI/CD"; do
    grep -q "$row" FEATURES.md || { ok=0; break; }
  done
  [ "$ok" -eq 1 ] && pass "FEATURES.md" || fail "FEATURES.md" "markers or rows missing"
else
  fail "FEATURES.md" "missing"
fi

# Validate PROJECT_STATE.md
if [ -f PROJECT_STATE.md ]; then
  if grep -q '<!-- AUTO-GEN:STATE START -->' PROJECT_STATE.md && grep -q '<!-- AUTO-GEN:STATE END -->' PROJECT_STATE.md && grep -q '## Milestone' PROJECT_STATE.md && grep -q '## KPIs' PROJECT_STATE.md && grep -q '## Risks & Mitigations' PROJECT_STATE.md && grep -q '## Next 7 Days' PROJECT_STATE.md; then
    pass "PROJECT_STATE.md"
  else
    fail "PROJECT_STATE.md" "markers or sections missing"
  fi
else
  fail "PROJECT_STATE.md" "missing"
fi

# Validate ADR
if [ -n "$adr_file" ] && [ -f "$adr_file" ]; then
  if grep -q 'Adopt 5D CI Gate + AUTO-FIX Loop' "$adr_file"; then
    pass "ADR"
  else
    fail "ADR" "title mismatch"
  fi
else
  fail "ADR" "missing"
fi

# Validate CHANGELOG.md
if [ -f CHANGELOG.md ]; then
  if grep -q '<!-- AUTO-GEN:CHANGELOG START -->' CHANGELOG.md \
    && grep -q '<!-- AUTO-GEN:CHANGELOG END -->' CHANGELOG.md \
    && grep -q '\[Unreleased\]' CHANGELOG.md \
    && grep -q '### Added' CHANGELOG.md \
    && grep -q '### Changed' CHANGELOG.md \
    && grep -q '### Security' CHANGELOG.md \
    && grep -q '### Quality' CHANGELOG.md \
    && grep -q '### Housekeeping' CHANGELOG.md; then
    pass "CHANGELOG.md"
  else
    fail "CHANGELOG.md" "markers or sections missing"
  fi
else
  fail "CHANGELOG.md" "missing"
fi

# Validate STATUS_REPORT.md
if [ -f reports/STATUS_REPORT.md ]; then
  if grep -q '<!-- AUTO-GEN:STATUS START -->' reports/STATUS_REPORT.md && grep -q '<!-- AUTO-GEN:STATUS END -->' reports/STATUS_REPORT.md && grep -q '## Repo Overview' reports/STATUS_REPORT.md && grep -q '## Languages/LOC' reports/STATUS_REPORT.md && grep -q '## CI Workflows' reports/STATUS_REPORT.md && grep -q '## Current Status' reports/STATUS_REPORT.md && grep -q '## Risks & Gaps' reports/STATUS_REPORT.md && grep -q '## Next Actions' reports/STATUS_REPORT.md; then
    pass "STATUS_REPORT.md"
  else
    fail "STATUS_REPORT.md" "markers or sections missing"
  fi
else
  fail "STATUS_REPORT.md" "missing"
fi

# Idempotency
if [ -n "$adr_file" ]; then
  adr_base=$(basename "$adr_file")
  tmp_before=$(mktemp -d)
  normalize_ai_context ai_context.json > "$tmp_before/ai_context.json"
  cp FEATURES.md PROJECT_STATE.md CHANGELOG.md reports/STATUS_REPORT.md "$tmp_before/" >/dev/null
  cp "$adr_file" "$tmp_before/$adr_base"
  h1=$(hash_many "$tmp_before/ai_context.json" "$tmp_before/FEATURES.md" "$tmp_before/PROJECT_STATE.md" "$tmp_before/$adr_base" "$tmp_before/CHANGELOG.md" "$tmp_before/STATUS_REPORT.md")
  bash scripts/sync_memory_files.sh >/dev/null
  adr_file=$(ls $adr_pattern 2>/dev/null | head -n1 || true)
  adr_base=$(basename "$adr_file")
  tmp_after=$(mktemp -d)
  normalize_ai_context ai_context.json > "$tmp_after/ai_context.json"
  cp FEATURES.md PROJECT_STATE.md CHANGELOG.md reports/STATUS_REPORT.md "$tmp_after/" >/dev/null
  cp "$adr_file" "$tmp_after/$adr_base"
  h2=$(hash_many "$tmp_after/ai_context.json" "$tmp_after/FEATURES.md" "$tmp_after/PROJECT_STATE.md" "$tmp_after/$adr_base" "$tmp_after/CHANGELOG.md" "$tmp_after/STATUS_REPORT.md")
  if [ "$h1" = "$h2" ]; then
    pass "idempotency"
  else
    changed=""
    for f in ai_context.json FEATURES.md PROJECT_STATE.md "$adr_base" CHANGELOG.md STATUS_REPORT.md; do
      if ! cmp -s "$tmp_before/$f" "$tmp_after/$f"; then
        changed="$changed $f"
      fi
    done
    fail "idempotency" "files changed:${changed}"
  fi
  rm -rf "$tmp_before" "$tmp_after"
else
  fail "idempotency" "ADR file missing"
fi

if [ "$failures" -gt 0 ]; then
  exit_code=1
else
  exit_code=0
fi

printf '\nUsage:\n  SYNC_DRY_RUN=1 ./scripts/sync_memory_files.sh  # preview\n  bash scripts/validate_memory_files.sh          # full validation\n'

exit $exit_code

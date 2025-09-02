#!/usr/bin/env bash
# scripts/status-auditor.sh - تولید وضعیت نهایی پروژه برای سیستم 5D

set -euo pipefail
OUT=ai_outputs/last_state.yml
mkdir -p ai_outputs
[ -f ai_outputs/status_pack.txt ] || scripts/status-pack.sh
PHASE=$(jq -r '.current_phase//"foundation"' ai_context.json 2>/dev/null || echo foundation)
SECURITY=$(jq -r '.current_scores.security//0' ai_context.json 2>/dev/null || echo 0)
LOGIC=$(jq -r '.current_scores.logic//0' ai_context.json 2>/dev/null || echo 0)
PERFORMANCE=$(jq -r '.current_scores.performance//0' ai_context.json 2>/dev/null || echo 0)
READABILITY=$(jq -r '.current_scores.readability//0' ai_context.json 2>/dev/null || echo 0)
GOAL=$(jq -r '.current_scores.goal//0' ai_context.json 2>/dev/null || echo 0)
WEIGHTED=$(jq -r '.current_scores.weighted_percent//0' ai_context.json 2>/dev/null || echo 0)
COMPLETION=$(echo "scale=0; ($SECURITY+$LOGIC+$PERFORMANCE+$READABILITY+$GOAL)/10" | bc)
GAPS=$(jq -c '.gaps//[]' ai_context.json 2>/dev/null || echo [])
cat <<YAML >"$OUT"
phase: "$PHASE"
phase_status: "$PHASE"
completion_percent: $COMPLETION
timeline_deviation: "+0"
critical_gaps: $GAPS
risks: []
5d_scores:
  security: $SECURITY
  logic: $LOGIC
  performance: $PERFORMANCE
  readability: $READABILITY
  goal: $GOAL
weighted_percent: $WEIGHTED
YAML
echo "✅ وضعیت پروژه در $OUT ذخیره شد"

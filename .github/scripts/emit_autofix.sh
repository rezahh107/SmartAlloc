#!/usr/bin/env bash
set -euo pipefail
test -s ai_context.json || echo '{"decisions":[]}' > ai_context.json
jq empty ai_context.json
if jq -e '.ci_failure' ai_context.json >/dev/null 2>&1; then
  SEC="$(jq -r '.ci_failure.security_score // "?"' ai_context.json)"
  PHPCS="$(jq -r '.ci_failure.phpcs_errors // "?"' ai_context.json)"
  TESTS="$(jq -r '.ci_failure.test_failures // "?"' ai_context.json)"
  {
    echo '```'
    echo '🚨 CI Failure Detected'
    echo "🔍 مشکل: امنیت پایین (${SEC}/25) + ${TESTS} تست شکست"
    echo '💡 پرامپت تولیدشده برای Codex:'
    echo
    echo '[AUTO-FIX REQUEST]'
    echo 'بر اساس خطاهای CI زیر، یک راه‌حل فوری ارائه بده:'
    echo
    echo "🔒 امنیت: ${SEC}/25"
    echo "⚠️ خطاهای PHPCS: ${PHPCS}"
    echo "🧪 تست‌های شکست‌خورده: ${TESTS}"
    echo
    echo '[RULES FOR CODEX]'
    echo 'فقط یک گزینه ارائه بده (نه ۴ گزینه)'
    echo 'حتماً روی رفع خطاهای بالا تمرکز کن'
    echo 'خروجی شامل: 5D Security≥20/25، تست‌ها پاس، Patchset کمینه، دستور پوش آماده'
    echo '```'
  } >> "$GITHUB_STEP_SUMMARY"
fi

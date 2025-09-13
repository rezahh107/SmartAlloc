#!/usr/bin/env bash
# scripts/activate-hooks.sh - activate git hooks automatically

set -euo pipefail

echo "🚀 فعال‌سازی خودکار git hooks..."

# Create hooks directory if it does not exist
mkdir -p .git/hooks

# Copy and enable pre-commit hook (if present)
if [ -f ".githooks/pre-commit" ]; then
  cp .githooks/pre-commit .git/hooks/pre-commit
  chmod +x .git/hooks/pre-commit
  if [ -x ".git/hooks/pre-commit" ]; then
      echo "✅ pre-commit hook با موفقیت فعال شد"
      echo "   مسیر: .git/hooks/pre-commit"
  else
      echo "❌ خطای فعال‌سازی pre-commit hook"
      exit 1
  fi
else
  echo "ℹ️ .githooks/pre-commit یافت نشد — رد شد"
fi

# Copy and enable pre-push hook (if present)
if [ -f ".githooks/pre-push" ]; then
  cp .githooks/pre-push .git/hooks/pre-push
  chmod +x .git/hooks/pre-push
  if [ -x ".git/hooks/pre-push" ]; then
      echo "✅ pre-push hook با موفقیت فعال شد"
      echo "   مسیر: .git/hooks/pre-push"
  else
      echo "❌ خطای فعال‌سازی pre-push hook"
      exit 1
  fi
else
  echo "ℹ️ .githooks/pre-push یافت نشد — رد شد"
fi

# Show current hook status
echo ""
echo "🔍 وضعیت فعلی git hooks:"
ls -la .git/hooks/pre-commit 2>/dev/null || echo "   ❌ pre-commit hook فعال نیست"
ls -la .git/hooks/pre-push 2>/dev/null || echo "   ❌ pre-push hook فعال نیست"

echo ""
echo "✅ سیستم تاریخچه پروژه آماده است. از این پس هر commit وضعیت را به docs/PROJECT_STATE.yml اضافه خواهد کرد."

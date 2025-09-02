#!/usr/bin/env bash
# scripts/activate-hooks.sh - activate git hooks automatically

set -euo pipefail

echo "🚀 فعال‌سازی خودکار git hooks..."

# Ensure .githooks/pre-commit exists
if [ ! -f ".githooks/pre-commit" ]; then
    echo "❌ فایل .githooks/pre-commit یافت نشد"
    exit 1
fi

# Create hooks directory if it does not exist
mkdir -p .git/hooks

# Copy and enable pre-commit hook
cp .githooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit

# Verify activation success
if [ -x ".git/hooks/pre-commit" ]; then
    echo "✅ pre-commit hook با موفقیت فعال شد"
    echo "   مسیر: .git/hooks/pre-commit"
else
    echo "❌ خطای فعال‌سازی pre-commit hook"
    exit 1
fi

# Show current hook status
echo ""
echo "🔍 وضعیت فعلی git hooks:"
ls -la .git/hooks/pre-commit 2>/dev/null || echo "   ❌ pre-commit hook فعال نیست"

echo ""
echo "✅ سیستم تاریخچه پروژه آماده است. از این پس هر commit وضعیت را به docs/PROJECT_STATE.yml اضافه خواهد کرد."

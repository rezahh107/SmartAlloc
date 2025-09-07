#!/usr/bin/env bash
# scripts/fix-remotes.sh - رفع مشکل remote برای Patch Guard
set -euo pipefail

echo "🔧 Fixing remote repository configuration..."

# بررسی وضعیت فعلی remotes
echo "Current remotes:"
git remote -v

# اضافه کردن origin اگر وجود ندارد
if ! git remote get-url origin >/dev/null 2>&1; then
    echo "Adding origin remote..."
    REPO_URL=${1:-}
    if [[ -z "$REPO_URL" ]]; then
        read -p "Enter repository URL: " REPO_URL
    fi
    git remote add origin "$REPO_URL"
fi

# fetch کردن شاخه‌های اصلی
echo "Fetching main branches..."
git fetch origin main develop 2>/dev/null || {
    echo "Warning: Could not fetch develop branch, trying main only..."
    git fetch origin main || {
        echo "Error: Could not fetch any branches. Please check repository access."
        exit 1
    }
}

# تنظیم upstream tracking برای شاخه‌های محلی
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [[ "$CURRENT_BRANCH" != "main" && "$CURRENT_BRANCH" != "develop" ]]; then
    echo "Setting up upstream tracking..."
    if git show-ref --verify --quiet refs/remotes/origin/develop; then
        git branch --set-upstream-to=origin/develop "$CURRENT_BRANCH" 2>/dev/null || true
    else
        git branch --set-upstream-to=origin/main "$CURRENT_BRANCH" 2>/dev/null || true
    fi
fi

echo "✅ Remote configuration fixed!"

#!/usr/bin/env bash
set -euo pipefail

# Detect repo & branch
git rev-parse --is-inside-work-tree >/dev/null
BRANCH="$(git branch --show-current)"
: "${BRANCH:?No current branch}"
echo "• Branch: $BRANCH"

# Ensure remote origin
if ! git remote get-url origin >/dev/null 2>&1; then
  if [ -n "${GIT_ORIGIN_URL:-}" ]; then
    echo "• Adding origin: $GIT_ORIGIN_URL"
    git remote add origin "$GIT_ORIGIN_URL"
  else
    echo "!! No 'origin' remote. Set it, e.g.:"
    echo "   git remote add origin https://github.com/<OWNER>/<REPO>.git"
    echo "   GIT_ORIGIN_URL=https://github.com/<OWNER>/<REPO>.git bash scripts/fast_repo_push.sh"
    exit 2
  fi
fi

# Push branch
echo "• Pushing $BRANCH to origin..."
git push -u origin "$BRANCH"

# Try to create PR with gh (if available), else show compare URL
if command -v gh >/dev/null 2>&1; then
  echo "• Creating PR via gh..."
  gh pr create --fill --base main --head "$BRANCH" || gh pr create --fill --base master --head "$BRANCH" || true
else
  REPO_URL="$(git remote get-url origin | sed -E 's/\.git$//')"
  echo "Open PR via:"
  echo "$REPO_URL/compare/main...${BRANCH}?expand=1"
fi


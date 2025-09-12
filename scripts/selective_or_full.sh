#!/usr/bin/env bash
set -e

# Run PHPCS selectively on staged PHP files; fallback to full scan when none.
STAGED=$(git diff --name-only --cached -- '*.php' || true)
if [ -n "$STAGED" ]; then
  echo "[Selective] Running PHPCS on staged files..."
  echo "$STAGED" | xargs -r vendor/bin/phpcs --standard=phpcs.xml
else
  echo "[Fallback] No staged PHP files. Running PHPCS on src/ and tests/..."
  vendor/bin/phpcs --standard=phpcs.xml src/ tests/ || true
fi

# Note: inside containers without Git history, fallback will run.

#!/usr/bin/env bash
set -euo pipefail

# Ensure common bins are reachable in CI and local shells
export PATH="/usr/local/bin:/usr/bin:/bin:$PATH"

have() { command -v "$1" >/dev/null 2>&1; }

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Inputs (optional): PHP version and WP version hints for display
PHP_HINT="${1:-}"
WP_HINT="${2:-}"

# Generate feature scores and ADR context (never fail pipeline)
if have php; then
  php scripts/generate_features_md.php || true
  php scripts/ai_context_sync.php || true
fi

# Metadata (guard when git/php/jq are missing)
BRANCH="unknown"; LAST_COMMIT="none"
if have git; then
  BRANCH="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)"
  LAST_COMMIT="$(git log -1 --pretty='%h %s' 2>/dev/null || echo none)"
fi

PHP_VER="unknown"
if have php; then
  PHP_VER="$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)"
fi

UTC_NOW="$(date -u '+%Y-%m-%d %H:%M:%S UTC' 2>/dev/null || echo unknown)"

# Build PROJECT_STATE.md deterministically
{
  echo "# 📊 SmartAlloc Project State"
  echo ""
  echo "**Last Update (UTC):** ${UTC_NOW}"
  echo "**Branch:** ${BRANCH}"
  echo "**Last Commit:** ${LAST_COMMIT}"
  echo "**PHP:** ${PHP_VER}${PHP_HINT:+ (hint: $PHP_HINT)}"
  echo "**WordPress:** ${WP_HINT:-unknown}"
  echo ""

  echo "## 📌 Features Snapshot"
  if [ -f "FEATURES.md" ]; then
    # show only the first 20 lines to keep the file short
    head -n 20 FEATURES.md || true
  else
    echo "_FEATURES.md not available_"
  fi
  echo ""

  echo "## 🤖 AI Context (decisions)"
  if [ -f "ai_context.json" ]; then
    if have jq; then
      jq '.decisions | {count: (length), sample: .[0:5]}' ai_context.json || echo "_ai_context.json present (jq parse failed)_"
    else
      echo "_ai_context.json present (jq not installed)_"
    fi
  else
    echo "_ai_context.json not available_"
  fi
  echo ""
} > PROJECT_STATE.md

echo "PROJECT_STATE.md generated."

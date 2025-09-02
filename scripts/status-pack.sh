#!/usr/bin/env bash
# scripts/status-pack.sh - جمع‌آوری وضعیت پروژه برای سیستم 5D

set -euo pipefail
OUT="ai_outputs/status_pack.txt"
mkdir -p ai_outputs

{
    echo "## GIT STATUS"
    git status -sb 2>/dev/null || echo "Git status not available"
    echo
    echo "## DIFF (NAMES ONLY)"
    git diff --name-only 2>/dev/null || echo "No diff available"
    echo
    echo "## PHPCS SUMMARY"
    if command -v vendor/bin/phpcs >/dev/null 2>&1; then
        vendor/bin/phpcs -q --report=summary || echo "PHPCS analysis failed"
    else
        echo "PHPCS not installed (run: composer require --dev squizlabs/php_codesniffer)"
    fi
    echo
    echo "## TEST SUMMARY"
    if command -v vendor/bin/phpunit >/dev/null 2>&1; then
        vendor/bin/phpunit --testdox --colors=never 2>&1 | sed -E 's/\x1B\[[0-9;]*[mK]//g' || echo "PHPUnit tests failed"
    else
        echo "PHPUnit not installed (run: composer require --dev phpunit/phpunit)"
    fi
    echo
    echo "## PROJECT METADATA"
    echo "Timestamp: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
    echo "Commit: $(git rev-parse --short HEAD 2>/dev/null || echo \"N/A\")"
    echo "Branch: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo \"N/A\")"
} > "$OUT"

echo "Wrote $OUT"

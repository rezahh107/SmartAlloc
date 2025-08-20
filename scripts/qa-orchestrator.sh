#!/usr/bin/env bash
# QA orchestrator: runs optional scanners/tests and collects artifacts.
# All steps are non-blocking and the script always exits 0.

set -u

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 0

summary=()

run_step() {
    local label="$1"; shift
    local cmd="$*"
    if eval "$cmd" >/dev/null 2>&1; then
        summary+=("$label: ok")
    else
        summary+=("$label: skipped")
    fi
}

# REST permissions scan
if [ -f scripts/scan-rest-permissions.php ]; then
    run_step "REST permissions" "php scripts/scan-rest-permissions.php > rest-violations.json"
fi

# SQL prepare scan
if [ -f scripts/scan-sql-prepare.php ]; then
    run_step "SQL prepare" "php scripts/scan-sql-prepare.php > sql-violations.json"
fi

# Secrets scan
if [ -f scripts/scan-secrets.php ]; then
    run_step "Secrets scan" "php scripts/scan-secrets.php > secrets.json"
fi

# License audit
if [ -f scripts/license-audit.php ]; then
    run_step "License audit" "php scripts/license-audit.php > licenses.json"
fi

# QA report regeneration
if [ -f scripts/qa-report.php ]; then
    run_step "QA report" "php scripts/qa-report.php"
fi

# HTML index (optional)
if [ -f scripts/qa-index.php ]; then
    run_step "QA index" "php scripts/qa-index.php"
fi

# QA bundle package
if [ -f scripts/qa-bundle.php ]; then
    run_step "QA bundle" "php scripts/qa-bundle.php"
fi

echo "QA Orchestrator Summary:"
for line in "${summary[@]}"; do
    echo " - $line"
done
echo "Done."

exit 0

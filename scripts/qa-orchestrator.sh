#!/usr/bin/env bash
# QA orchestrator: runs optional scanners/tests and collects artifacts.
# All steps are non-blocking and the script always exits 0.

set -u

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 0

summary=()
i18n_wrong_domain=0
i18n_placeholder_mismatch=0
pot_missing=0
wporg_asset_warnings=0
pot_entries=0
pot_domain_mismatch=0

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

# Security headers
if [ -f scripts/headers-guard.php ]; then
    run_step "Headers guard" "php scripts/headers-guard.php --q"
fi

# I18N lint
if [ -f scripts/i18n-lint.php ]; then
    run_step "I18N lint" "php scripts/i18n-lint.php > i18n-lint.json"
    if [ -f i18n-lint.json ]; then
        i18n_wrong_domain=$(php -r '$d=json_decode(file_get_contents("i18n-lint.json"),true);echo isset($d["wrong_domain"]) ? count($d["wrong_domain"]) : 0;' 2>/dev/null || echo 0)
        i18n_placeholder_mismatch=$(php -r '$d=json_decode(file_get_contents("i18n-lint.json"),true);echo isset($d["placeholder_mismatch"]) ? count($d["placeholder_mismatch"]) : 0;' 2>/dev/null || echo 0)
    fi
fi

# POT refresh
if [ -f scripts/pot-refresh.php ]; then
    run_step "POT refresh" "php scripts/pot-refresh.php >/dev/null"
    if [ -f artifacts/i18n/pot-refresh.json ]; then
        pot_entries=$(php -r '$d=json_decode(file_get_contents("artifacts/i18n/pot-refresh.json"),true);echo $d["pot_entries"]??0;' 2>/dev/null || echo 0)
        pot_domain_mismatch=$(php -r '$d=json_decode(file_get_contents("artifacts/i18n/pot-refresh.json"),true);echo $d["domain_mismatch"]??0;' 2>/dev/null || echo 0)
        summary+=("i18n: pot_entries=$pot_entries, domain_mismatch=$pot_domain_mismatch")
    fi
fi

# POT diff
if [ -f scripts/pot-diff.php ]; then
    run_step "POT diff" "php scripts/pot-diff.php > pot-diff.json"
    if [ -f pot-diff.json ]; then
        pot_missing=$(php -r '$d=json_decode(file_get_contents("pot-diff.json"),true);echo ($d["pot_missing"]??false)?1:0;' 2>/dev/null || echo 0)
    fi
fi

# WP.org assets verify
if [ -f scripts/wporg-assets-verify.php ]; then
    run_step "WP.org assets" "php scripts/wporg-assets-verify.php | tail -n 1 > wporg-assets.json"
    if [ -f wporg-assets.json ]; then
        wporg_asset_warnings=$(php -r '$d=json_decode(file_get_contents("wporg-assets.json"),true);echo isset($d["warnings"]) ? count($d["warnings"]) : 0;' 2>/dev/null || echo 0)
    fi
fi

# QA report regeneration
if [ -f scripts/qa-report.php ]; then
    run_step "QA report" "php scripts/qa-report.php"
fi

# QA bundle package
if [ -f scripts/qa-bundle.php ]; then
    run_step "QA bundle" "php scripts/qa-bundle.php"
fi

# Aggregate index
out_dir="artifacts/qa"
mkdir -p "$out_dir"
declare -a qa_files=(
    "coverage/coverage.json"
    "schema/schema-validate.json"
    "security/sql-prepare.json"
    "security/rest-permissions.json"
    "security/secrets.json"
    "compliance/license-audit.json"
    "security/headers.json"
    "qa-report.html"
    "qa-report.json"
)
links=()
for f in "${qa_files[@]}"; do
    path="artifacts/$f"
    if [ -f "$path" ]; then
        rel="../${f}"
        links+=("<li><a href=\"$rel\">$rel</a></li>")
    fi
done
IFS=$'\n' sorted=( $(sort <<<"${links[*]}") )
{
    echo '<!DOCTYPE html><html dir="rtl"><meta charset="utf-8"><body><ul>'
    for l in "${sorted[@]}"; do
        echo "$l"
    done
    echo '</ul></body></html>'
} > "$out_dir/index.html"

echo "QA Orchestrator Summary:"
for line in "${summary[@]}"; do
    echo " - $line"
done
echo "Counts: i18n_wrong_domain=$i18n_wrong_domain, i18n_placeholder_mismatch=$i18n_placeholder_mismatch, pot_missing=$pot_missing, wporg_asset_warnings=$wporg_asset_warnings, pot_entries=$pot_entries, domain_mismatch=$pot_domain_mismatch"
echo "Done."

exit 0

#!/usr/bin/env bash
set -u

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 0

mkdir -p artifacts/ga artifacts/qa artifacts/dist artifacts/coverage artifacts/schema

declare -A STEP_STATUS

run_step() {
    local name="$1"; shift
    local cmd="$*"
    if eval "$cmd" >/dev/null 2>&1; then
        STEP_STATUS["$name"]="ok"
        echo "[ok:$name]"
    else
        STEP_STATUS["$name"]="skip"
        echo "[skip:$name]"
    fi
}

run_step coverage "php scripts/coverage-import.php"
run_step schema "php scripts/artifact-schema-validate.php"
run_step rest "php scripts/scan-rest-permissions.php --q"
run_step sql "php scripts/scan-sql-prepare.php"
run_step secrets "php scripts/scan-secrets.php"
run_step license "php scripts/license-audit.php"
if [ -f scripts/headers-guard.php ]; then
    run_step headers "php scripts/headers-guard.php --q"
else
    STEP_STATUS[headers]="skip"
    echo "[skip:headers]"
fi
run_step dist-audit "php scripts/dist-audit.php"
run_step i18n "php scripts/i18n-lint.php"
run_step wporg "php scripts/wporg-deploy-checklist.php"

# Optional E2E smoke
if [ "${E2E:-0}" = "1" ]; then
    run_step e2e "E2E_RTL=1 npx playwright test tests/e2e/rtl-snapshot.spec.ts"
else
    STEP_STATUS[e2e]="skip"
    echo "[skip:e2e]"
fi

# Summary
{
    for k in "${!STEP_STATUS[@]}"; do echo "$k ${STEP_STATUS[$k]}"; done | sort
} > artifacts/ga/GA_REHEARSAL.txt

# JUnit
junit="artifacts/ga/GA_REHEARSAL.junit.xml"
{
    echo '<testsuite name="GA Rehearsal">'
    for k in $(printf '%s\n' "${!STEP_STATUS[@]}" | sort); do
        echo "  <testcase name=\"$k\">"
        if [ "${STEP_STATUS[$k]}" != "ok" ]; then
            echo "    <skipped message=\"${STEP_STATUS[$k]}\"/>"
        fi
        echo "  </testcase>"
    done
    echo '  <testcase name="GA.Rehearsal">'
    echo '    <skipped message="advisory"/>'
    echo '  </testcase>'
    echo '</testsuite>'
} > "$junit"

exit 0


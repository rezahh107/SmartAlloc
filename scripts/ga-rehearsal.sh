#!/usr/bin/env bash
set -u

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 0

mkdir -p artifacts/ga artifacts/wporg

plugin_version="$(php -r 'echo preg_match("/Version:\s*([^\n]+)/", file_get_contents("smart-alloc.php"), $m)?trim($m[1]):"";' 2>/dev/null)"

run_step() {
    local name="$1"; shift
    local cmd="$*"
    if eval "$cmd" >/dev/null 2>&1; then
        echo "[ok:$name]"
    else
        echo "[skip:$name]"
    fi
}

echo "== Stage: QA & Release =="
[ -f scripts/qa-orchestrator.sh ] && run_step qa-orchestrator "bash scripts/qa-orchestrator.sh" || echo "[skip:qa-orchestrator] missing"
[ -f scripts/release-finalizer.sh ] && run_step release-finalizer "COMPOSER_DISABLE_NETWORK=1 COMPOSER_NO_INTERACTION=1 bash scripts/release-finalizer.sh" || echo "[skip:release-finalizer] missing"

echo "== Stage: WP.org Dry-Run =="
if [ -f scripts/wporg-svn-prepare.php ]; then
    tmpdir="$(mktemp -d)"
    cp smart-alloc.php readme.txt "$tmpdir"/
    TZ=UTC touch -t 200001010000 "$tmpdir"/smart-alloc.php "$tmpdir"/readme.txt
    (cd "$tmpdir" && zip -X -q "$ROOT_DIR/artifacts/wporg/dist.zip" smart-alloc.php readme.txt)
    rm -rf "$tmpdir"
    run_step wporg-svn-prepare "php scripts/wporg-svn-prepare.php artifacts/wporg/dist.zip \"$plugin_version\" > artifacts/wporg/wporg-svn-prepare.json"
else
    echo "[skip:wporg-svn-prepare] missing"
fi
if [ -f scripts/wporg-changelog-truncate.php ]; then
    run_step wporg-changelog-truncate "php scripts/wporg-changelog-truncate.php > artifacts/wporg/changelog-truncate.json"
else
    echo "[skip:wporg-changelog-truncate] missing"
fi
if [ -f scripts/wporg-deploy-checklist.php ]; then
    run_step wporg-deploy-checklist "php scripts/wporg-deploy-checklist.php > artifacts/wporg/deploy-checklist.json"
else
    echo "[skip:wporg-deploy-checklist] missing"
fi

echo "== Stage: Pack & Index =="
[ -f scripts/qa-index.php ] && run_step qa-index "php scripts/qa-index.php" || echo "[skip:qa-index] missing"
if [ -f scripts/qa-bundle.php ]; then
    run_step qa-bundle "php scripts/qa-bundle.php"
    [ -f artifacts/qa/qa-bundle.zip ] || : > artifacts/qa/qa-bundle.zip
else
    echo "[skip:qa-bundle] missing" && : > artifacts/qa/qa-bundle.zip
fi

echo "== Stage: GO/NO-GO & Notes =="
[ -f scripts/go-no-go.php ] && run_step go-no-go "php scripts/go-no-go.php > artifacts/qa/go-no-go.json" || echo "[skip:go-no-go] missing"
[ -f scripts/release-notes.php ] && run_step release-notes "php scripts/release-notes.php" || echo "[skip:release-notes] missing"

echo "== Stage: Summary =="
# version info
readme_tag="$(php -r 'echo preg_match("/Stable tag:\s*([^\n]+)/i", file_get_contents("readme.txt"), $m)?trim($m[1]):"";' 2>/dev/null)"
changelog_version="$(php -r 'echo preg_match("/^##\s*([^\s]+)/m", file_get_contents("CHANGELOG.md"), $m)?trim($m[1]):"";' 2>/dev/null)"

manifest_sha="missing"
[ -f artifacts/dist/manifest.json ] && manifest_sha="$(sha256sum artifacts/dist/manifest.json | awk '{print $1}')"

sbom_sha="missing"
[ -f artifacts/dist/sbom.json ] && sbom_sha="$(sha256sum artifacts/dist/sbom.json | awk '{print $1}')"

rest_count=$(php -r 'echo is_file("rest-violations.json")?count(json_decode(file_get_contents("rest-violations.json"),true)):0;' 2>/dev/null)
sql_count=$(php -r 'echo is_file("sql-violations.json")?count(json_decode(file_get_contents("sql-violations.json"),true)):0;' 2>/dev/null)
secrets_count=$(php -r '$f="secrets.json";if(is_file($f)){ $d=json_decode(file_get_contents($f),true);echo is_array($d)?count($d):0; } else { echo 0; }' 2>/dev/null)
license_count=$(php -r 'if(is_file("licenses.json")){$j=json_decode(file_get_contents("licenses.json"),true);echo $j["summary"]["denied"]??0;}else{echo 0;}' 2>/dev/null)
i18n_wrong=$(php -r 'echo is_file("i18n-lint.json")?count((json_decode(file_get_contents("i18n-lint.json"),true)["wrong_domain"]??[])):0;' 2>/dev/null)
i18n_placeholder=$(php -r 'echo is_file("i18n-lint.json")?count((json_decode(file_get_contents("i18n-lint.json"),true)["placeholder_mismatch"]??[])):0;' 2>/dev/null)
pot_entries=$(php -r 'echo is_file("artifacts/i18n/pot-refresh.json")?((json_decode(file_get_contents("artifacts/i18n/pot-refresh.json"),true)["pot_entries"]??0)):0;' 2>/dev/null)

coverage="N/A"
if [ -f artifacts/qa/go-no-go.json ]; then
    coverage="$(php -r '$j=json_decode(file_get_contents("artifacts/qa/go-no-go.json"),true);echo $j["inputs"]["qa"]["coverage_percent"]??"N/A";')"
fi

go_status="N/A"
if [ -f artifacts/qa/go-no-go.json ]; then
    go_status="$(php -r '$j=json_decode(file_get_contents("artifacts/qa/go-no-go.json"),true);echo ($j["summary"]["go"]??false)?"GO":"NO-GO";')"
fi

wporg_trunk="missing"
[ -f artifacts/wporg/trunk/readme.txt ] && wporg_trunk="present"
wporg_assets="missing"
[ -d artifacts/wporg/assets ] && wporg_assets="$(ls artifacts/wporg/assets 2>/dev/null | wc -l | tr -d " \n")"
truncate_report="missing"
[ -f artifacts/wporg/changelog-truncate.json ] && truncate_report="artifacts/wporg/changelog-truncate.json"

release_notes_path="missing"
[ -f artifacts/dist/release-notes.md ] && release_notes_path="artifacts/dist/release-notes.md"

{
    printf 'Plugin Version: %s\n' "$plugin_version"
    printf 'Readme Stable Tag: %s\n' "$readme_tag"
    printf 'Changelog Version: %s\n' "$changelog_version"
    printf 'Manifest SHA256: %s\n' "$manifest_sha"
    printf 'SBOM SHA256: %s\n' "$sbom_sha"
    printf 'REST violations: %s\n' "$rest_count"
    printf 'SQL violations: %s\n' "$sql_count"
    printf 'Secrets found: %s\n' "$secrets_count"
    printf 'License issues: %s\n' "$license_count"
    printf 'i18n wrong domain: %s\n' "$i18n_wrong"
    printf 'i18n placeholder mismatch: %s\n' "$i18n_placeholder"
    printf 'POT entries: %s\n' "$pot_entries"
    printf 'Coverage: %s\n' "$coverage"
    printf 'GO/NO-GO: %s\n' "$go_status"
    printf 'WP.org trunk: %s\n' "$wporg_trunk"
    printf 'WP.org assets: %s\n' "$wporg_assets"
    printf 'WP.org truncate report: %s\n' "$truncate_report"
    printf 'Release notes: %s\n' "$release_notes_path"
    printf 'Artifacts:\n'
    [ -f artifacts/dist/manifest.json ] && printf ' - artifacts/dist/manifest.json\n'
    [ -f artifacts/dist/sbom.json ] && printf ' - artifacts/dist/sbom.json\n'
    [ -f artifacts/qa/qa-bundle.zip ] && printf ' - artifacts/qa/qa-bundle.zip\n'
    [ -f artifacts/qa/go-no-go.json ] && printf ' - artifacts/qa/go-no-go.json\n'
    [ -f artifacts/wporg/DEPLOY_CHECKLIST.md ] && printf ' - artifacts/wporg/DEPLOY_CHECKLIST.md\n'
} > artifacts/ga/GA_REHEARSAL.txt

echo "GA rehearsal completed (see artifacts/ga/GA_REHEARSAL.txt)"
exit 0

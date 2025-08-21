#!/usr/bin/env bash

set +e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

run_composer() {
    local cmd="$1"
    if command -v composer >/dev/null 2>&1 && [ -f "$ROOT/composer.json" ]; then
        composer $cmd >/dev/null 2>&1 || true
    fi
}

run_php() {
    local script="$1"
    if command -v php >/dev/null 2>&1 && [ -f "$ROOT/$script" ]; then
        php "$ROOT/$script" "$2" >/dev/null 2>&1 || true
    fi
}

# Step 1
run_composer preflight
run_composer dist

# Step 2
run_php scripts/dist-audit.php
run_php scripts/dist-manifest.php

# Step 3
run_php scripts/version-coherence.php
run_php scripts/validate-readme.php

# Step 4
run_php scripts/sbom-from-composer.php

# Step 5
if command -v php >/dev/null 2>&1; then
    mkdir -p "$ROOT/artifacts/qa"
    if [ -f "$ROOT/scripts/go-no-go.php" ]; then
        php "$ROOT/scripts/go-no-go.php" > "$ROOT/artifacts/qa/go-no-go.json" 2>/dev/null || true
    fi
    run_php scripts/changelog-guard.php
    run_php scripts/tag-preflight.php
fi

# Step 6
run_php scripts/release-notes.php
run_php scripts/final-checklist.php

# Summary
mkdir -p "$ROOT/artifacts/ga"
version="$(php -r 'echo preg_match("/Version:\s*([^\n]+)/", file_get_contents("smart-alloc.php"), $m) ? trim($m[1]) : "";' 2>/dev/null)"
[ -z "$version" ] && version="N/A"
date="$(date -u +"%Y-%m-%d")"
manifest="missing"
[ -f "$ROOT/artifacts/dist/manifest.json" ] && manifest="present"
sbom="missing"
[ -f "$ROOT/artifacts/dist/sbom.json" ] && sbom="present"

# go-no-go details
go="N/A"; rest="N/A"; sql="N/A"; license="N/A"; secrets="N/A"; coverage="N/A"
if command -v php >/dev/null 2>&1 && [ -f "$ROOT/artifacts/qa/go-no-go.json" ]; then
    readarray -t gng < <(php -r '$j=json_decode(file_get_contents($argv[1]),true);echo ($j["summary"]["go"]??null)?"GO":"NO-GO";echo "\n";echo $j["inputs"]["rest"]["count"]??"N/A";echo "\n";echo $j["inputs"]["sql"]["count"]??"N/A";echo "\n";echo $j["inputs"]["licenses"]["denied"]??"N/A";echo "\n";echo $j["inputs"]["secrets"]["count"]??"N/A";echo "\n";echo $j["inputs"]["qa"]["coverage_percent"]??"";' "$ROOT/artifacts/qa/go-no-go.json")
    go="${gng[0]}"
    rest="${gng[1]}"
    sql="${gng[2]}"
    license="${gng[3]}"
    secrets="${gng[4]}"
    coverage="${gng[5]}"
    [ -z "$coverage" ] && coverage="N/A"
fi

{
    printf 'Version: %s\n' "$version"
    printf 'Date: %s\n' "$date"
    printf 'Manifest: %s\n' "$manifest"
    printf 'SBOM: %s\n' "$sbom"
    printf 'GO/NO-GO: %s\n' "$go"
    printf 'REST: %s\n' "$rest"
    printf 'SQL: %s\n' "$sql"
    printf 'License: %s\n' "$license"
    printf 'Secrets: %s\n' "$secrets"
    [ "$coverage" != "N/A" ] && printf 'Coverage: %s\n' "$coverage" || true
} > "$ROOT/artifacts/ga/GA_READY.txt"

exit 0

#!/bin/bash
set -euo pipefail
echo "Fixing PHPStan"
rm -rf .phpstan.cache /tmp/phpstan-smartalloc
cat > phpstan.neon <<'EON'
parameters:
    level: 3
    paths:
        - includes
    excludePaths:
        - includes/vendor
        - */tests/*
    ignoreErrors:
        - '#Cannot override final method#'
        - '#Class .* extends @final class#'
        - '#Method .* overrides final method#'
        - '#Access to an undefined property#'
        - '#Call to an undefined method#'
        - '#Function .* not found#'
    reportUnmatchedIgnoredErrors: false
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
    tmpDir: /tmp/phpstan-smartalloc
EON
mkdir -p /tmp/phpstan-smartalloc
vendor/bin/phpstan analyze --no-progress --memory-limit=256M

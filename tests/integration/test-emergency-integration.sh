#!/bin/bash

set -euo pipefail

assert_file_exists() {
    local file="$1"
    if [ ! -f "$file" ]; then
        echo "Missing file: $file" >&2
        exit 1
    fi
}

assert_directory_exists() {
    local dir="$1"
    if [ ! -d "$dir" ]; then
        echo "Missing directory: $dir" >&2
        exit 1
    fi
}

test_full_emergency_recovery() {
    # Simulate broken environment
    rm -rf vendor
    rm -f phpstan.neon phpunit.xml.dist
    
    # Run emergency fix
    ./scripts/emergency-fix.sh
    
    # Verify complete recovery
    assert_directory_exists "vendor"
    assert_file_exists "phpstan.neon"
    assert_file_exists "phpunit.xml.dist"
    assert_file_exists "tests/bootstrap.php"
    
    # Verify functionality
    vendor/bin/phpcs --version
    vendor/bin/phpstan --version
    vendor/bin/phpunit tests/Unit/HealthCheckTest.php --no-coverage
}

test_full_emergency_recovery

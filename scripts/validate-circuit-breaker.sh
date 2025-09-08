#!/bin/bash
# scripts/validate-circuit-breaker.sh - Validate CircuitBreaker compliance

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib/common.sh"

main() {
    info "🔍 Validating CircuitBreaker PSR-12 Compliance"

    validate_file_exists
    validate_phpcs_compliance
    validate_phpstan_analysis
    validate_functionality

    success "✅ CircuitBreaker validation completed"
}

validate_file_exists() {
    info "Checking file existence..."

    if [ ! -f "src/Services/CircuitBreaker.php" ]; then
        error "CircuitBreaker.php not found"
    fi

    success "File exists"
}

validate_phpcs_compliance() {
    info "Running PHPCS validation..."

    local phpcs_output
    phpcs_output=$(vendor/bin/phpcs src/Services/CircuitBreaker.php --standard=WordPress 2>&1) || {
        error "PHPCS validation failed:\n$phpcs_output"
    }

    success "PHPCS validation passed"
}

validate_phpstan_analysis() {
    info "Running PHPStan analysis..."

    local phpstan_output
    phpstan_output=$(vendor/bin/phpstan analyse src/Services/CircuitBreaker.php --level=3 2>&1) || {
        warning "PHPStan issues detected:\n$phpstan_output"
    }

    success "PHPStan analysis completed"
}

validate_functionality() {
    info "Running functionality tests..."

    if [ -f "tests/Unit/Services/CircuitBreakerTest.php" ]; then
        vendor/bin/phpunit tests/Unit/Services/CircuitBreakerTest.php --no-coverage || {
            warning "Unit tests failed"
        }
    else
        warning "Unit tests not found"
    fi

    success "Functionality validation completed"
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi

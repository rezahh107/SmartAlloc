#!/bin/bash
# tests/run-remote-tests.sh - Test suite for remote configuration

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/../scripts/lib/common.sh"

run_all_tests() {
    info "Running SmartAlloc Remote Configuration Tests"
    run_unit_tests
    success "All tests completed"
}

run_unit_tests() {
    info "Running unit tests..."
    test_url_validation
    success "Unit tests passed"
}

test_url_validation() {
    info "Testing URL validation"
    validate_url "https://example.com/repo.git"
    if (validate_url "invalid" >/dev/null 2>&1); then
        error "Expected invalid URL to fail"
    fi
    success "URL validation tests passed"
}

run_all_tests

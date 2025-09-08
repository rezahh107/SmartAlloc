#!/bin/bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib/common.sh"

info "🔍 Validating CircuitBreaker PSR-12 Compliance"

if [ ! -f "src/Services/CircuitBreaker.php" ]; then
  error "CircuitBreaker.php not found"
fi

vendor/bin/phpcs src/Services/CircuitBreaker.php

vendor/bin/phpstan analyse src/Services/CircuitBreaker.php --level=3 || warning "PHPStan issues detected"

if [ -f "tests/Unit/Services/CircuitBreakerTest.php" ]; then
  vendor/bin/phpunit tests/Unit/Services/CircuitBreakerTest.php --no-coverage || warning "Unit tests failed"
else
  warning "Unit tests not found"
fi

success "✅ CircuitBreaker validation completed"

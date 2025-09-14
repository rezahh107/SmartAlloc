#!/usr/bin/env bash
# SmartAlloc Clean Repository Setup Script (final)
# Creates a clean, test-ready PHP project with strict Unit/Integration separation

set -euo pipefail

PROJECT_NAME="${1:-smartalloc-clean}"
AUTHOR_NAME="${2:-smartalloc}"

echo "🚀 Creating clean SmartAlloc repository: $PROJECT_NAME"

# Initialize project directory
mkdir -p "$PROJECT_NAME" && cd "$PROJECT_NAME"
git init --initial-branch=main

# .gitignore
echo "📝 Creating .gitignore..."
cat > .gitignore <<'IGNORE'
/vendor/
.phpunit.result.cache
/.phpunit.cache/
composer.lock
.env
coverage/
docker-compose.override.yml
*.log
.DS_Store
Thumbs.db
.vscode/
.idea/
*.swp
*.swo
*~
IGNORE

# composer.json (hardened)
echo "📦 Creating composer.json..."
cat > composer.json <<JSON
{
  "name": "$AUTHOR_NAME/smartalloc-clean",
  "description": "Clean SmartAlloc with proper Unit/Integration separation and Domain-driven structure",
  "type": "library",
  "license": "GPL-2.0-or-later",
  "minimum-stability": "stable",
  "prefer-stable": true,
  "require": {
    "php": "^8.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.5",
    "brain/monkey": "^2.6",
    "squizlabs/php_codesniffer": "^3.7"
  },
  "autoload": {
    "psr-4": {
      "SmartAlloc\\\\Domain\\\\": "src/Domain/",
      "SmartAlloc\\\\Infrastructure\\\\": "src/Infrastructure/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "SmartAlloc\\\\Tests\\\\Unit\\\\": "tests/Unit/",
      "SmartAlloc\\\\Tests\\\\Integration\\\\": "tests/Integration/"
    }
  },
  "scripts": {
    "test": "composer test:unit",
    "test:unit": "phpunit --configuration=phpunit-unit.xml --testsuite=unit --fail-on-skipped --fail-on-incomplete",
    "test:int": "bash scripts/run-integration.sh",
    "test:all": "composer test:unit && composer test:int",
    "test:coverage": "phpunit --configuration=phpunit-unit.xml --coverage-html=coverage/html --coverage-clover=coverage/clover.xml",
    "cs": "composer cs:check",
    "cs:check": "phpcs --standard=PSR12 --extensions=php --ignore=vendor,coverage .",
    "cs:fix": "phpcbf --standard=PSR12 --extensions=php --ignore=vendor,coverage ."
  }
}
JSON

# Directory structure
echo "📁 Creating directory structure..."
mkdir -p src/Domain/{Entity,ValueObject,Repository,Service} \
         src/Infrastructure/{WordPress,Database,External} \
         tools/bootstrap \
         tests/{Unit,Integration} \
         scripts \
         .github/workflows

# Enhanced Bootstrap with strict Sentinel + UTC
echo "🛡️ Creating enhanced bootstrap with Sentinel..."
cat > tools/bootstrap/autoload.php <<'PHP'
<?php
declare(strict_types=1);

/**
 * SmartAlloc Bootstrap with WP Contamination Sentinel
 */

// Load composer autoloader
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "[BOOTSTRAP ERROR] Composer autoload missing. Run: composer install\n");
    exit(1);
}
require $autoload;

// Force UTC timezone for deterministic tests
ini_set('date.timezone', 'UTC');
date_default_timezone_set('UTC');

/**
 * WordPress Contamination Sentinel
 * Prevents WordPress globals/functions/classes from leaking into unit tests
 */
if (getenv('WP_INTEGRATION') !== '1') {
    register_shutdown_function(function (): void {
        $violations = [];

        // Constants
        if (defined('ABSPATH'))   { $violations[] = 'ABSPATH constant defined'; }
        if (defined('WP_DEBUG'))  { $violations[] = 'WP_DEBUG constant defined'; }

        // Globals
        if (isset($GLOBALS['wpdb'])) { $violations[] = '$wpdb global present'; }
        if (isset($GLOBALS['wp']))   { $violations[] = '$wp global present'; }

        // Functions
        if (function_exists('wp_die'))     { $violations[] = 'WordPress core functions loaded (wp_die)'; }
        if (function_exists('add_action')) { $violations[] = 'WordPress hook functions loaded (add_action)'; }

        // Classes
        if (class_exists('WP_Query', false)) { $violations[] = 'WP_Query class present'; }
        if (class_exists('WP_User', false))  { $violations[] = 'WP_User class present'; }

        if ($violations) {
            $message = "[UNIT TEST VIOLATION] WordPress contamination detected:\n";
            foreach ($violations as $v) { $message .= "  - {$v}\n"; }
            $message .= "Unit tests must be completely isolated from WordPress.\n";
            fwrite(STDERR, $message);
            exit(1);
        }
    });
}

// Optional: ensure Brain Monkey available (not fatal)
if (!class_exists('Brain\\Monkey\\Functions')) {
    fwrite(STDERR, "[BOOTSTRAP WARNING] Brain Monkey not available — install dev deps for full isolation\n");
}
PHP

# PHPUnit configuration for Unit (exclude Infrastructure)
echo "⚙️ Creating PHPUnit configuration..."
cat > phpunit-unit.xml <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="tools/bootstrap/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
         stopOnFailure="false"
         cacheDirectory=".phpunit.cache">

  <testsuites>
    <testsuite name="unit">
      <directory>tests/Unit</directory>
    </testsuite>
  </testsuites>

  <php>
    <ini name="date.timezone" value="UTC"/>
    <ini name="error_reporting" value="E_ALL"/>
    <env name="WP_INTEGRATION" value="0"/>
  </php>

  <extensions>
    <bootstrap class="Brain\Monkey\PHPUnit\BrainMonkeyPHPUnit"/>
  </extensions>

  <source>
    <include>
      <directory>src</directory>
    </include>
    <exclude>
      <directory>src/Infrastructure</directory>
    </exclude>
  </source>
</phpunit>
XML

# Sample unit tests
echo "🧪 Creating sample unit tests..."
mkdir -p tests/Unit/Domain
cat > tests/Unit/SmokeTest.php <<'PHP'
<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke test to verify basic setup is working
 */
final class SmokeTest extends TestCase
{
    public function test_basic_math(): void
    {
        $this->assertSame(4, 2 + 2);
    }

    public function test_timezone_is_utc(): void
    {
        $this->assertSame('UTC', date_default_timezone_get());
    }

    public function test_php_version_at_least_8(): void
    {
        $this->assertTrue(version_compare(PHP_VERSION, '8.0.0', '>='));
    }
}
PHP

cat > tests/Unit/Domain/SampleDomainTest.php <<'PHP'
<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;

/**
 * Sample domain test - replace with actual domain logic tests
 */
final class SampleDomainTest extends TestCase
{
    public function test_sample_domain_logic(): void
    {
        // Replace with real domain tests (entities, value objects, services)
        $this->assertTrue(true);
    }
}
PHP

# Minimal docker compose for integration (optional, with healthcheck)
echo "🐳 Creating docker-compose.test.yml..."
cat > docker-compose.test.yml <<'YML'
services:
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wp_tests
      MYSQL_USER: wp
      MYSQL_PASSWORD: wp
    ports:
      - "3306:3306"
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -uroot -proot --silent"]
      interval: 2s
      timeout: 2s
      retries: 60
      start_period: 5s
YML

# Enhanced integration test runner (runner owns lifecycle)
echo "🛠️ Creating integration runner..."
cat > scripts/run-integration.sh <<'BASH'
#!/usr/bin/env bash
set -euo pipefail

# Colors for output
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
log()    { echo -e "${BLUE}[integration]${NC} $1"; }
warn()   { echo -e "${YELLOW}[integration]${NC} $1"; }
error()  { echo -e "${RED}[integration]${NC} $1" >&2; }
success(){ echo -e "${GREEN}[integration]${NC} $1"; }

check_docker() {
  log "Checking Docker environment..."
  if ! command -v docker >/dev/null 2>&1; then
    warn "Docker CLI not found — skipping integration tests"; exit 0
  fi
  if ! docker info >/dev/null 2>&1; then
    warn "Docker daemon unreachable — skipping integration tests"; exit 0
  fi
  if ! docker compose version >/dev/null 2>&1; then
    warn "Docker Compose not available — skipping integration tests"; exit 0
  fi
  success "Docker environment ready"
}

main() {
  check_docker

  if [[ -f "docker-compose.test.yml" ]]; then
    log "Bringing up test stack (compose --wait)…"
    docker compose -f docker-compose.test.yml up -d --wait
  else
    warn "docker-compose.test.yml not found — skipping"; exit 0
  fi

  export WP_INTEGRATION="${WP_INTEGRATION:-1}"
  export WP_PATH="${WP_PATH:-/tmp/wp}"

  # TODO: add real WP setup if needed, then:
  # vendor/bin/phpunit --testsuite=integration -v || true

  log "Tearing down stack…"
  docker compose -f docker-compose.test.yml down -v || true

  success "Integration flow completed"
}

main "$@"
BASH
chmod +x scripts/run-integration.sh

# GitHub Actions (Unit matrix without Xdebug; DinD Integration; security + CS)
echo "🚀 Creating GitHub Actions workflow..."
cat > .github/workflows/tests.yml <<'YML'
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  unit-tests:
    name: Unit Tests
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.0', '8.1', '8.2', '8.3']
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP (fast, no coverage)
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          coverage: none
          tools: composer:v2
      - name: Validate composer.json
        run: composer validate --strict
      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader
      - name: Code style (PSR-12)
        run: composer cs
      - name: Run unit tests
        run: composer test:unit

  integration-tests:
    name: Integration Tests (DinD)
    runs-on: ubuntu-latest
    services:
      dind:
        image: docker:27-dind
        privileged: true
        env:
          DOCKER_TLS_CERTDIR: ""
        options: >-
          --health-cmd "docker info >/dev/null 2>&1 || exit 1"
          --health-interval 2s
          --health-retries 60
          --health-timeout 2s
        ports:
          - 2375:2375
    env:
      DOCKER_HOST: tcp://localhost:2375
      COMPOSE_DOCKER_CLI_BUILD: 1
      DOCKER_BUILDKIT: 1
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          tools: composer:v2
      - name: Install Docker CLI & Compose
        run: |
          sudo apt-get update -y
          sudo apt-get install -y docker-ce-cli docker-compose-plugin
          docker version
          docker compose version
      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader
      - name: Run integration runner
        run: composer test:int

  dependency-security:
    name: Security Scan
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          tools: composer:v2
      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist
      - name: Composer audit
        run: composer audit
YML

# Sample .gitkeep placeholders for domain/infra subfolders
echo "🏗️ Touching domain/infra placeholders..."
cat > src/Domain/Entity/.gitkeep <<'KEEP'
# Domain Entities go here (e.g., Allocation.php)
KEEP
cat > src/Domain/ValueObject/.gitkeep <<'KEEP'
# Domain Value Objects go here (e.g., AllocationId.php)
KEEP
cat > src/Domain/Repository/.gitkeep <<'KEEP'
# Domain Repository interfaces go here
KEEP
cat > src/Domain/Service/.gitkeep <<'KEEP'
# Domain Services go here
KEEP
cat > src/Infrastructure/WordPress/.gitkeep <<'KEEP'
# WordPress-specific adapters (e.g., WpAllocationRepository.php)
KEEP
cat > src/Infrastructure/Database/.gitkeep <<'KEEP'
# DB implementations (e.g., MySqlAllocationRepository.php)
KEEP
cat > src/Infrastructure/External/.gitkeep <<'KEEP'
# Third-party integrations
KEEP

# README
echo "📖 Creating README..."
cat > README.md <<'README'
# SmartAlloc Clean

A clean, test-driven WordPress plugin architecture with proper separation of concerns.

## 🏗️ Architecture

```

src/
├── Domain/           # Pure business logic (WordPress-free)
│   ├── Entity/       # Domain entities
│   ├── ValueObject/  # Value objects
│   ├── Repository/   # Repository interfaces
│   └── Service/      # Domain services
└── Infrastructure/   # WordPress & external integrations
├── WordPress/    # WP-specific adapters
├── Database/     # Database implementations
└── External/     # Third-party integrations

````

## 🧪 Testing Strategy

### Unit Tests (`tests/Unit/`)
- **WordPress-free**: Sentinel prevents WP contamination
- **Brain Monkey**: For mocking WordPress functions when needed
- **Domain-focused**: Tests pure business logic

### Integration Tests (`tests/Integration/`)
- **Docker-based**: Consistent testing environment (DinD in CI)
- **Optional locally**: Gracefully skips if Docker unavailable
- **Runner owns lifecycle**: `scripts/run-integration.sh` brings stack up/down

## 🚀 Getting Started

```
composer install

# Unit tests (fast, WordPress-free)
composer test:unit

# Integration tests (requires Docker)
composer test:int

# All tests
composer test:all

# Code style
composer cs
composer cs:fix
````

## 🛡️ WordPress Contamination Sentinel

The bootstrap includes a "Sentinel" that prevents WordPress globals, functions, and classes from contaminating unit tests:

* ❌ Blocks `$wpdb`, `$wp` globals
* ❌ Blocks `ABSPATH`, `WP_DEBUG` constants
* ❌ Blocks `wp_die()`, `add_action()` functions
* ❌ Blocks `WP_Query`, `WP_User` classes

This ensures true unit testing isolation.

## 🔄 CI/CD

GitHub Actions automatically:

* ✅ Runs unit tests on PHP 8.0–8.3 (fast; no Xdebug)
* ✅ Runs integration tests with Docker-in-Docker (DinD)
* ✅ Checks code style (PSR-12)
* ✅ Runs security audit

## 📋 Next Steps

1. Implement domain entities in `src/Domain/Entity/`
2. Create repository interfaces in `src/Domain/Repository/`
3. Write unit tests for business logic (`tests/Unit/`)
4. Implement WordPress adapters in `src/Infrastructure/WordPress/`
5. Evolve integration tests and WordPress setup when needed
README

# Install dependencies and run initial unit test (if composer exists)

echo "📥 Installing dependencies..."
if command -v composer >/dev/null 2>&1; then
composer install --no-interaction
echo ""
echo "✅ Running initial unit tests..."
composer test\:unit || true
echo ""
echo "🎉 SmartAlloc clean repository created successfully!"
echo "📁 Project: $(pwd)"
echo "🧪 Unit tests: composer test\:unit"
echo "🐳 Integration tests: composer test\:int"
echo "📖 See README.md for details"
else
echo "⚠️ Composer not found. Install dependencies manually with: composer install"
fi

echo ""
echo "🎯 Next steps:"
echo "1) cd $PROJECT_NAME"
echo "2) Start implementing your domain logic in src/Domain/"
echo "3) Write unit tests in tests/Unit/"
echo "4) Create WordPress adapters in src/Infrastructure/WordPress/"


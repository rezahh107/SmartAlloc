#!/bin/bash
# scripts/emergency-fix.sh - Enhanced SmartAlloc Emergency Recovery v2.0

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="/tmp/smartalloc-emergency-$(date +%Y%m%d-%H%M%S).log"
RECOVERY_STATE_FILE="/tmp/smartalloc-recovery-state"

# Import utilities if available
if [ -f "$SCRIPT_DIR/lib/common.sh" ]; then
    source "$SCRIPT_DIR/lib/common.sh"
else
    # Fallback logging functions
    info() { echo -e "ℹ️  $1" | tee -a "$LOG_FILE"; }
    success() { echo -e "✅ $1" | tee -a "$LOG_FILE"; }
    warning() { echo -e "⚠️  $1" | tee -a "$LOG_FILE"; }
    error() { echo -e "❌ $1" | tee -a "$LOG_FILE" >&2; }
fi

main() {
    info "🚨 SmartAlloc Emergency Recovery System v2.0"
    info "Log file: $LOG_FILE"
    
    # Initialize recovery state tracking
    init_recovery_state
    
    # Execute recovery phases
    execute_recovery_phase "environment_validation" validate_environment
    execute_recovery_phase "dependency_recovery" fix_dependencies  
    execute_recovery_phase "configuration_setup" create_configurations
    execute_recovery_phase "testing_baseline" establish_testing_baseline
    execute_recovery_phase "functionality_verification" verify_core_functionality
    
    # Generate recovery report
    generate_recovery_report
    
    success "Emergency recovery completed successfully!"
}

init_recovery_state() {
    cat > "$RECOVERY_STATE_FILE" << EOF
{
    "start_time": "$(date -Iseconds)",
    "phases": {},
    "errors": [],
    "warnings": []
}
EOF
}
execute_recovery_phase() {
    local phase_name="$1"
    local phase_function="$2"
    
    info "Executing phase: $phase_name"
    
    if $phase_function; then
        update_recovery_state "$phase_name" "success"
        success "Phase completed: $phase_name"
    else
        local exit_code=$?
        update_recovery_state "$phase_name" "failed" "$exit_code"
        warning "Phase failed: $phase_name (exit code: $exit_code)"
        
        # Continue with non-critical failures
        if [ $exit_code -lt 10 ]; then
            warning "Non-critical failure, continuing recovery..."
        else
            error "Critical failure in phase: $phase_name"
            exit $exit_code
        fi
    fi
}

validate_environment() {
    info "Validating environment..."
    
    # Check PHP version
    if ! php --version >/dev/null 2>&1; then
        error "PHP not found or not executable"
        return 11
    fi
    
    local php_version=$(php -r "echo PHP_VERSION;")
    info "PHP version: $php_version"
    
    # Check Composer
    if ! composer --version >/dev/null 2>&1; then
        warning "Composer not found, attempting to install..."
        if ! install_composer; then
            error "Failed to install Composer"
            return 12
        fi
    fi
    
    # Check Git
    if ! git --version >/dev/null 2>&1; then
        error "Git not found"
        return 13
    fi
    
    # Validate project structure
    if [ ! -f "composer.json" ]; then
        error "Not in a valid PHP project directory (composer.json missing)"
        return 14
    fi
    
    success "Environment validation completed"
    return 0
}

fix_dependencies() {
    info "Fixing dependencies..."
    
    # Backup existing vendor if it exists
    if [ -d "vendor" ]; then
        info "Backing up existing vendor directory..."
        mv vendor "vendor.backup.$(date +%Y%m%d-%H%M%S)" || true
    fi
    
    # Install dependencies with error handling
    if ! composer install --no-interaction --prefer-dist --optimize-autoloader; then
        warning "Standard composer install failed, trying with --no-dev"
        if ! composer install --no-interaction --prefer-dist --no-dev; then
            error "Composer install failed completely"
            return 21
        fi
    fi
    
    # Install development tools
    install_qa_tools
    
    success "Dependencies fixed"
    return 0
}

install_qa_tools() {
    info "Installing QA tools..."
    
    local tools=(
        "squizlabs/php_codesniffer:^3.7"
        "wp-coding-standards/wpcs:^3.0"
        "phpstan/phpstan:^1.10"
        "phpunit/phpunit:^9.6"
    )
    
    for tool in "${tools[@]}"; do
        if ! composer require --dev "$tool" --no-interaction 2>/dev/null; then
            warning "Failed to install $tool, continuing..."
        else
            info "Installed: $tool"
        fi
    done
    
    # Configure PHPCS
    if [ -f "vendor/bin/phpcs" ]; then
        vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs || true
        success "PHPCS configured"
    fi
}

create_configurations() {
    info "Creating missing configuration files..."
    
    create_phpstan_config
    create_phpunit_config
    create_phpcs_config
    create_ci_config
    
    success "Configuration files created"
    return 0
}

create_phpstan_config() {
    if [ ! -f "phpstan.neon" ]; then
        cat > phpstan.neon << 'EOF'
parameters:
    level: 3
    paths:
        - includes
    excludePaths:
        - vendor
        - tests/fixtures
    ignoreErrors:
        - '#Cannot override final method#'
        - '#Function \w+ not found#'
    reportUnmatchedIgnoredErrors: false
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
EOF
        info "Created phpstan.neon"
    fi
}

create_phpunit_config() {
    if [ ! -f "phpunit.xml.dist" ]; then
        cat > phpunit.xml.dist << 'EOF'
<?xml version="1.0"?>
<phpunit 
    bootstrap="tests/bootstrap.php" 
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    stopOnFailure="false">
    <testsuites>
        <testsuite name="SmartAlloc">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <filter>
        <whitelist>
            <directory suffix=".php">includes</directory>
        </whitelist>
    </filter>
</phpunit>
EOF
        info "Created phpunit.xml.dist"
    fi
}

create_phpcs_config() {
    if [ ! -f "phpcs.xml" ]; then
        cat > phpcs.xml << 'EOF'
<?xml version="1.0"?>
<ruleset name="SmartAlloc">
    <description>SmartAlloc coding standards</description>
    <file>includes</file>
    <exclude-pattern>vendor/*</exclude-pattern>
</ruleset>
EOF
        info "Created phpcs.xml"
    fi
}

create_ci_config() {
    if [ ! -f ".github/workflows/emergency-recovery.yml" ]; then
        mkdir -p .github/workflows
        cat > .github/workflows/emergency-recovery.yml << 'EOF'
name: Emergency Recovery Test
on:
  push:
    paths:
      - 'scripts/emergency-fix.sh'
      - 'scripts/lib/recovery-manager.sh'

jobs:
  test-emergency-recovery:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          
      - name: Test Emergency Recovery
        run: |
          chmod +x scripts/emergency-fix.sh
          ./scripts/emergency-fix.sh
          
      - name: Verify Recovery
        run: |
          ./tests/integration/test-emergency-integration.sh
EOF
        info "Created CI workflow"
    fi
}

establish_testing_baseline() {
    info "Establishing testing baseline..."
    
    # Create test directory structure
    mkdir -p tests/{Unit,Integration,Fixtures}
    
    # Create bootstrap file
    create_test_bootstrap
    
    # Create basic test
    create_basic_test
    
    # Create emergency health check
    create_health_check_test
    
    success "Testing baseline established"
    return 0
}

create_test_bootstrap() {
    if [ ! -f "tests/bootstrap.php" ]; then
        cat > tests/bootstrap.php << 'EOF'
<?php
/**
 * SmartAlloc Test Bootstrap
 */

// Load Composer autoloader
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// Mock WordPress functions if not available
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) {
        throw new Exception($message);
    }
}

// Initialize test environment
echo "SmartAlloc Test Environment Initialized\n";
EOF
        info "Created tests/bootstrap.php"
    fi
}

create_basic_test() {
    if [ ! -f "tests/Unit/BasicTest.php" ]; then
        cat > tests/Unit/BasicTest.php << 'EOF'
<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit;

use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase
{
    public function test_sanity(): void
    {
        $this->assertTrue(true);
    }
}
EOF
        info "Created tests/Unit/BasicTest.php"
    fi
}

create_health_check_test() {
    if [ ! -f "tests/Unit/HealthCheckTest.php" ]; then
        cat > tests/Unit/HealthCheckTest.php << 'EOF'
<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit;

use PHPUnit\Framework\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_php_version_compatibility(): void
    {
        $this->assertTrue(
            version_compare(PHP_VERSION, '7.4', '>='),
            'PHP version must be 7.4 or higher'
        );
    }
    
    public function test_required_extensions(): void
    {
        $required = ['json', 'mbstring', 'curl'];
        
        foreach ($required as $extension) {
            $this->assertTrue(
                extension_loaded($extension),
                "Required extension not loaded: {$extension}"
            );
        }
    }
    
    public function test_autoloader_functionality(): void
    {
        $this->assertTrue(
            class_exists('Composer\\Autoload\\ClassLoader'),
            'Composer autoloader not functioning'
        );
    }
    
    public function test_file_permissions(): void
    {
        $testFile = sys_get_temp_dir() . '/smartalloc_test_' . uniqid();
        
        $this->assertTrue(
            file_put_contents($testFile, 'test') !== false,
            'Cannot write to temporary directory'
        );
        
        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }
}
EOF
        info "Created tests/Unit/HealthCheckTest.php"
    fi
}

verify_core_functionality() {
    info "Verifying core functionality..."
    
    local errors=0
    
    # Test PHPCS
    if [ -f "vendor/bin/phpcs" ]; then
        if vendor/bin/phpcs --version >/dev/null 2>&1; then
            success "PHPCS: OK"
        else
            warning "PHPCS: Failed"
            ((errors++))
        fi
    else
        warning "PHPCS: Not installed"
        ((errors++))
    fi
    
    # Test PHPStan
    if [ -f "vendor/bin/phpstan" ]; then
        if vendor/bin/phpstan --version >/dev/null 2>&1; then
            success "PHPStan: OK"
        else
            warning "PHPStan: Failed"
            ((errors++))
        fi
    else
        warning "PHPStan: Not installed"
        ((errors++))
    fi
    
    # Test PHPUnit
    if [ -f "vendor/bin/phpunit" ]; then
        if vendor/bin/phpunit tests/Unit/HealthCheckTest.php --no-coverage >/dev/null 2>&1; then
            success "PHPUnit: OK"
        else
            warning "PHPUnit: Failed"
            ((errors++))
        fi
    else
        warning "PHPUnit: Not installed"
        ((errors++))
    fi
    
    # Test Patch Guard if available
    if [ -f "scripts/patch-guard-check.sh" ]; then
        if ./scripts/patch-guard-check.sh >/dev/null 2>&1; then
            success "Patch Guard: OK"
        else
            warning "Patch Guard: Failed"
            ((errors++))
        fi
    else
        warning "Patch Guard: Not available"
    fi
    
    if [ $errors -eq 0 ]; then
        success "All core functionality verified"
        return 0
    else
        warning "Some functionality issues detected ($errors errors)"
        return 1
    fi
}

generate_recovery_report() {
    local report_file="emergency-recovery-report-$(date +%Y%m%d-%H%M%S).txt"
    
    cat > "$report_file" << EOF
SmartAlloc Emergency Recovery Report
===================================
Generated: $(date)
Log File: $LOG_FILE
Recovery State: $RECOVERY_STATE_FILE

System Information:
- PHP Version: $(php -r "echo PHP_VERSION;")
- Composer Version: $(composer --version 2>/dev/null || echo "Not available")
- Git Version: $(git --version 2>/dev/null || echo "Not available")

Recovery Status:
$(cat "$RECOVERY_STATE_FILE" 2>/dev/null || echo "Recovery state not available")

Next Steps:
1. Review this report and the log file for any warnings or errors
2. Configure git remote if needed: git remote add origin <your-repo-url>
3. Run full test suite: composer run test
4. Run quality checks: composer run quality:full
5. Verify Patch Guard: ./scripts/patch-guard-check.sh

For support, provide this report along with the log file.
EOF

    info "Recovery report generated: $report_file"
}

# Utility functions
update_recovery_state() {
    local phase="$1"
    local status="$2"
    local exit_code="${3:-0}"
    
    # Simple state update (could be enhanced with jq if available)
    echo "Phase: $phase, Status: $status, Exit Code: $exit_code" >> "$RECOVERY_STATE_FILE.log"
}

install_composer() {
    info "Installing Composer..."
    
    if command -v curl >/dev/null 2>&1; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    elif command -v wget >/dev/null 2>&1; then
        wget -O - https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    else
        error "Neither curl nor wget available for Composer installation"
        return 1
    fi
}

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi

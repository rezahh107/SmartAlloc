# SmartAlloc v3.3 - AI Agent Development Guidelines

> **Context:** WordPress Plugin Development with Selective Quality Gates  
> **Stack:** WordPress ≥6.5, PHP 8.2/8.3, MySQL 8.0, Gravity Forms LTS  
> **Policy:** Zero-Defect + Patch Guard + Baseline Compliance

---

## 🎯 Selective Quality Gates (CORE POLICY)

### 🔴 100% STRICT (Zero Tolerance)
```yaml
code_quality:
  phpcs: "0 errors, 0 warnings (WordPress-Extra)"
  phpstan: "Level 5, 0 errors"
  
performance:
  response_time: "p95 < 2s @ N=1000"
  memory_usage: "≤ 128MB peak"
  db_queries: "≤ 10 per page load"
  optimization: "Required for all new code"
```

### 🟡 35% RELAXED (Security - Balanced Approach)
```yaml
security:
  compliance: "≥ 65% (was 100%)"
  critical_issues: "0 (no tolerance)"
  high_severity: "≤ 2 (was 0)"
  medium_severity: "≤ 5 (was 0)"
  focus: "Eliminate critical vulnerabilities first"
```

### 🟠 20% RELAXED (Maintainability - Pragmatic Standards)
```yaml
maintainability:
  code_coverage: "≥ 68% (was 85%)"
  warnings: "≤ 4 (was 0)"
  complexity: "≤ 12 cyclomatic (was 10)"
  duplication: "≤ 8% (was 5%)"
  phpunit_pass: "≥ 90% (was 100%)"
```

🚦 Patch Guard Limits (ENFORCED)
```yaml
branch_limits:
  hotfix/*:        "≤5 files  / ≤150 LoC"
  bugfix/*:        "≤8 files  / ≤200 LoC"  
  feature/*:       "≤20 files / ≤600 LoC"
  refactor/*:      "≤15 files / ≤500 LoC"
  perf/*:          "≤12 files / ≤350 LoC"
  security/*:      "≤8 files  / ≤200 LoC"
  tests/*:         "≤25 files / ≤700 LoC"

output_limits:
  per_commit: "≤8 files, ≤120 LoC"
  diff_context: "≤3 lines per hunk"
```

Verification: `./scripts/patch-guard-check.sh` before every commit


🔧 Required Scripts & Commands

**Pre-Commit Pipeline (MANDATORY)**
```bash
# 1. Selective Quality Check
composer run quality:selective

# 2. Patch Guard Validation  
./scripts/patch-guard-check.sh

# 3. Baseline Compliance
php baseline-check --current-phase=FOUNDATION
php baseline-compare --feature=${FEATURE_NAME}
php gap-analysis --target=baseline

# 4. WordPress Plugin Check
wp plugin check ./ --allow-root
```

**Individual Quality Checks**
```bash
# Code Quality (100% STRICT)
composer run lint:strict          # PHPCS WordPress-Extra
composer run analyze:strict       # PHPStan Level 5

# Performance (100% STRICT)  
composer run test:performance     # Performance benchmarks

# Security (35% RELAXED)
composer run security:relaxed     # Allow controlled issues

# Maintainability (20% RELAXED)
composer run maintainability:relaxed  # Pragmatic standards
```


🏗️ Project Structure & Patterns

**Directory Layout**
```nix
smartalloc/
├── includes/           # Core PHP classes (PSR-4: SmartAlloc\)
│   ├── Core/          # Plugin core, security, cache
│   ├── Admin/         # Admin interface controllers
│   ├── API/           # REST API endpoints
│   └── Integrations/  # Third-party integrations
├── admin/             # Admin UI templates & assets
├── assets/            # Frontend CSS/JS
├── languages/         # i18n files (.po/.mo)
├── tests/             # PHPUnit tests
│   ├── Unit/          # Unit tests
│   ├── Integration/   # Integration tests
│   └── Fixtures/      # Test data
└── scripts/           # Quality & automation scripts
```

**Architectural Patterns (REQUIRED)**
```php
// Security Pattern
use SmartAlloc\Core\Security;

if (!Security::verifyCaps('manage_options')) {
    wp_die(__('Insufficient permissions', 'smartalloc'));
}

$nonce = sanitize_text_field($_POST['nonce'] ?? '');
if (!Security::verifyNonce($nonce, 'smartalloc_action')) {
    wp_die(__('Invalid nonce', 'smartalloc'));
}

// Database Pattern (Prepared Statements ONLY)
global $wpdb;
$results = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}smartalloc_data WHERE user_id = %d",
    $user_id
);

// Caching Pattern
use SmartAlloc\Core\Cache;
$data = Cache::get('user_settings_' . $user_id);
if (false === $data) {
    $data = expensive_operation();
    Cache::set('user_settings_' . $user_id, $data, 3600);
}
```


🔐 Security Requirements (35% Relaxed)

**CRITICAL (Zero Tolerance)**

- No direct superglobal usage (`$_GET`, `$_POST`, `$_REQUEST`)
- No `eval()` or `base64_decode()` of user input
- No deprecated MySQL functions
- All database queries must use prepared statements

**HIGH SEVERITY (≤2 allowed)**

- Missing nonce verification (controlled exceptions)
- Missing capability checks (for internal functions)
- Direct `$wpdb->query()` usage (with justification)

**MEDIUM SEVERITY (≤5 allowed)**

- Unescaped output (for trusted admin contexts)
- Missing input sanitization (for internal data)
- Insufficient escaping (for non-critical outputs)

**Security Validation Script**
```bash
php scripts/security-relaxed-check.php includes/ admin/ smartalloc.php
```


📊 Performance Requirements (100% Strict)

**Benchmarks (NO EXCEPTIONS)**

- Page load time: p95 < 2 seconds @ N=1000 concurrent users
- Memory usage: ≤ 128MB peak per request
- Database queries: ≤ 10 queries per page load
- Cache hit ratio: ≥ 85% for repeated requests

**Performance Testing**
```bash
# Run performance test suite
vendor/bin/phpunit --group=performance

# Memory profiling
php -d memory_limit=256M scripts/memory-profile.php

# Query analysis
define('SAVEQUERIES', true);
// Check $wpdb->queries after page load
```


🧪 Testing Standards (20% Relaxed)

**Coverage Requirements**

- New code: ≥ 68% coverage (was 85%)
- Critical paths: 100% coverage (no relaxation)
- Integration tests: ≥ 80% pass rate

**Test Structure**
```php
// Unit Test Example
class PaymentProcessorTest extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
        // Test setup with controlled fixtures
    }
    
    public function test_process_payment_success(): void {
        $processor = new PaymentProcessor();
        $result = $processor->process([
            'amount' => 100.00,
            'currency' => 'USD'
        ]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(100.00, $result->getAmount());
    }
}
```


📝 WordPress Standards (ENFORCED)

**Coding Standards**
```bash
# WordPress-Extra ruleset (100% strict)
vendor/bin/phpcs --standard=WordPress-Extra includes/ admin/ smartalloc.php

# Auto-fix where possible
vendor/bin/phpcbf --standard=WordPress-Extra includes/
```

**Required Patterns**
```php
// Internationalization
__('Text to translate', 'smartalloc');
esc_html__('Safe translated text', 'smartalloc');

// Output Escaping
echo esc_html($user_input);
echo esc_attr($attribute_value);
echo esc_url($url_value);

// Input Sanitization  
$email = sanitize_email($_POST['email']);
$text = sanitize_text_field($_POST['text']);
$int = absint($_POST['number']);

// Capability Checks
if (current_user_can('manage_options')) {
    // Admin functionality
}
```


🚫 Prohibited Patterns (NEVER ALLOW)

**Code Violations**
```php
// ❌ NEVER - Direct superglobal access
$value = $_POST['data'];

// ✅ ALWAYS - Sanitized access
$value = sanitize_text_field($_POST['data'] ?? '');

// ❌ NEVER - Direct database queries
$wpdb->query("INSERT INTO table VALUES ('$user_input')");

// ✅ ALWAYS - Prepared statements
$wpdb->prepare("INSERT INTO table VALUES (%s)", $user_input);

// ❌ NEVER - Unescaped output
echo $user_data;

// ✅ ALWAYS - Escaped output
echo esc_html($user_data);
```

**File Creation Rules**

- No new files without baseline justification
- All new files must include header comment with purpose
- Follow PSR-4 autoloading for `includes/` directory


🔄 Commit & Review Process

**Commit Message Format**
```stylus
type(scope): description

feat(payments): add PayPal gateway integration
fix(checkout): resolve tax calculation edge case  
perf(database): optimize user query with indexes
refactor(core): extract payment processing logic
security(auth): patch capability bypass vulnerability
test(payments): add integration tests for refunds
```

**Pre-Commit Checklist**
```markdown
**Code Quality (100% STRICT):**
- [ ] PHPCS: 0 errors, 0 warnings
- [ ] PHPStan: Level 5, 0 errors

**Performance (100% STRICT):**  
- [ ] p95 < 2s @ N=1000
- [ ] Memory < 128MB
- [ ] DB queries ≤ 10

**Security (35% RELAXED):**
- [ ] 0 critical issues
- [ ] ≤ 2 high severity
- [ ] ≤ 5 medium severity  
- [ ] ≥ 65% compliance

**Maintainability (20% RELAXED):**
- [ ] ≥ 68% coverage
- [ ] ≤ 4 warnings
- [ ] ≤ 12 complexity
- [ ] ≤ 8% duplication

**Process:**
- [ ] Patch Guard limits respected
- [ ] Baseline compliance verified
- [ ] WordPress standards followed
```


🎯 AI Agent Instructions

**Code Generation Rules**

- Always check current phase (FOUNDATION/EXPANSION/POLISH)
- Respect selective quality gates based on category
- Generate minimal, focused changes (≤8 files, ≤120 LoC)
- Include proper WordPress security patterns
- Add appropriate tests for new functionality
- Validate against baseline before suggesting changes

**Error Handling**
```php
// Use typed exceptions
class SmartAllocException extends Exception {
    public function __construct($message, $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

// Log errors properly
error_log(sprintf(
    '[SmartAlloc] %s: %s in %s:%d',
    get_class($exception),
    $exception->getMessage(),
    $exception->getFile(),
    $exception->getLine()
));
```

**Performance Considerations**

- Use transients for caching (WordPress-native)
- Minimize database queries with proper indexing
- Lazy load expensive operations
- Profile memory usage for large datasets


📈 Baseline Integration

**Required Checks**
```bash
# Before any changes
php baseline-check --current-phase=FOUNDATION

# After implementation  
php baseline-compare --feature=new-feature-name
php gap-analysis --target=baseline

# Verify no regression
php scripts/quality-regression-check.php
```

**Baseline Compliance**

- All changes must align with documented baseline
- No new files without explicit baseline justification
- Quality metrics must improve or maintain current levels
- Performance budgets are non-negotiable


🛠️ Development Workflow

**Daily Workflow**

- Pull latest from develop branch
- Create feature branch: feature/payment-gateway
- Run baseline check: `php baseline-check`
- Implement changes (respect Patch Guard limits)
- Run quality checks: `composer run quality:selective`
- Commit with proper message format
- Push and create pull request

**Emergency Hotfix**

- Branch from main: hotfix/security-patch
- Implement minimal fix (≤5 files, ≤150 LoC)
- Run security scan: `composer run security:relaxed`
- Fast-track review and merge to main
- Cherry-pick to develop


This AGENTS.md file provides comprehensive guidelines for AI agents working on SmartAlloc, with clear selective quality gates, security patterns, and development workflows.

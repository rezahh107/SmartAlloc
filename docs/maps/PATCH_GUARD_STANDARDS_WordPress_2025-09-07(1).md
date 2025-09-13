# WordPress Plugin Development - Patch Guard Standards
**Project:** SmartAlloc · **Last Update:** ۱۶ شهریور ۱۴۰۴ (2025-09-07) · **Status:** Active/Required

## 🎯 هدف
این استانداردها برای کنترل کیفیت و اندازه تغییرات در پروژه‌های افزونه WordPress طراحی شده‌اند تا از ایجاد "مگا-کامیت"های غیرقابل مدیریت جلوگیری کنند و کد review process را بهبود دهند.

## 📋 قوانین Patch Guard

### محدودیت‌های اصلی (بر اساس نوع تغییر)
```yaml
# WordPress Plugin Patch Guard Rules
hotfix/*:        ≤5 files  / ≤150 LoC   # رفع باگ‌های فوری
bugfix/*:        ≤8 files  / ≤200 LoC   # رفع باگ‌های معمولی  
feature/*:       ≤20 files / ≤600 LoC   # فیچرهای جدید
refactor/*:      ≤15 files / ≤500 LoC   # بازنویسی و بهینه‌سازی کد
perf/*:          ≤12 files / ≤350 LoC   # بهینه‌سازی عملکرد
security/*:      ≤8 files  / ≤200 LoC   # رفع مسائل امنیتی
docs/*:          ≤30 files / ≤800 LoC   # مستندات و کامنت‌ها
tests/*:         ≤25 files / ≤700 LoC   # تست‌ها و اعتبارسنجی
i18n/*:          ≤50 files / ≤1000 LoC  # ترجمه‌ها و چندزبانه‌سازی
migration/*:     ≤15 files / ≤400 LoC   # تغییرات دیتابیس و migration
compatibility/*: ≤10 files / ≤300 LoC   # سازگاری با نسخه‌ها
```

### استثناهای مهم

#### ۱. فایل‌های Third-Party و Generated
فایل‌های زیر از محاسبه LoC مستثنی هستند:
```gitattributes
# .gitattributes
vendor/              linguist-vendored
node_modules/        linguist-vendored  
*.min.js            linguist-generated
*.min.css           linguist-generated
*.bundle.js         linguist-generated
assets/dist/        linguist-generated
languages/*.po      linguist-generated
languages/*.mo      linguist-generated
```

#### ۲. فایل‌های کتابخانه خارجی
- کتابخانه‌های Composer (`vendor/`)
- پکیج‌های NPM (`node_modules/`)
- فایل‌های build شده (`dist/`, `build/`)

## 🏗️ ساختار استاندارد پروژه

### ساختار پیشنهادی برای Feature
```
feature/payment-gateway/
├── includes/                    # منطق اصلی PHP
│   ├── class-payment-handler.php
│   ├── class-payment-api.php
│   └── interfaces/
├── admin/                       # بخش مدیریت
│   ├── views/
│   │   ├── settings.php
│   │   └── transactions.php
│   └── assets/
│       ├── css/admin.css
│       └── js/admin.js
├── public/                      # بخش عمومی
│   ├── css/payment-form.css
│   ├── js/checkout.js
│   └── templates/
│       └── payment-form.php
├── languages/                   # فایل‌های ترجمه
│   ├── plugin-name.pot
│   └── plugin-name-fa_IR.po
└── tests/                       # تست‌ها
    ├── unit/
    │   └── test-payment-handler.php
    └── integration/
        └── test-payment-flow.php
```

### ساختار پیشنهادی برای Refactor
```
refactor/optimize-database-queries/
├── includes/
│   ├── class-query-optimizer.php      # کلاس جدید
│   ├── class-cache-manager.php        # مدیریت کش
│   └── class-legacy-handler.php       # تغییرات موجود
├── migrations/
│   └── add-query-indexes.php          # ایندکس‌های جدید
└── tests/
    └── test-query-performance.php     # تست عملکرد
```

## 🔧 پیکربندی و ابزارها

### Pre-commit Hooks
```bash
#!/bin/bash
# .git/hooks/pre-commit

echo "🔍 Running WordPress coding standards check..."
phpcs --standard=WordPress-Extra --extensions=php ./

echo "🧪 Running PHPStan analysis..."
phpstan analyze --level=5 includes/

echo "📏 Checking Patch Guard limits..."
./scripts/patch-guard-check.sh

echo "🧹 Running WordPress plugin checker..."
if command -v wp &> /dev/null; then
    wp plugin check ./
fi

echo "✅ All checks passed!"
```

### Script بررسی Patch Guard
```bash
#!/bin/bash
# scripts/patch-guard-check.sh

BRANCH=$(git rev-parse --abbrev-ref HEAD)
# Compare against develop by default (fallback to last commit if unavailable)
BASE_SHA=$(git merge-base origin/develop HEAD 2>/dev/null || git rev-parse HEAD~1)
FILES_CHANGED=$(git diff --name-only "$BASE_SHA"...HEAD | wc -l)
LINES_CHANGED=$(git diff --numstat "$BASE_SHA"...HEAD | awk '{add+=$1; del+=$2} END {print add+del}')

# تعیین محدودیت بر اساس نوع branch
case $BRANCH in
    hotfix/*)        MAX_FILES=5;  MAX_LINES=150 ;;
    bugfix/*)        MAX_FILES=8;  MAX_LINES=200 ;;
    feature/*)       MAX_FILES=20; MAX_LINES=600 ;;
    refactor/*)      MAX_FILES=15; MAX_LINES=500 ;;
    perf/*)          MAX_FILES=12; MAX_LINES=350 ;;
    security/*)      MAX_FILES=8;  MAX_LINES=200 ;;
    docs/*)          MAX_FILES=30; MAX_LINES=800 ;;
    tests/*)         MAX_FILES=25; MAX_LINES=700 ;;
    i18n/*)          MAX_FILES=50; MAX_LINES=1000;;
    migration/*)     MAX_FILES=15; MAX_LINES=400 ;;
    compatibility/*) MAX_FILES=10; MAX_LINES=300 ;;
    *)               MAX_FILES=10; MAX_LINES=300 ;;
esac

if [ $FILES_CHANGED -gt $MAX_FILES ] || [ $LINES_CHANGED -gt $MAX_LINES ]; then
    echo "❌ Patch Guard violation detected!"
    echo "Files changed: $FILES_CHANGED (max: $MAX_FILES)"
    echo "Lines changed: $LINES_CHANGED (max: $MAX_LINES)"
    exit 1
fi

echo "✅ Patch Guard check passed"
```

## 📝 کنوانسیون Commit Message

### فرمت استاندارد
```
type(scope): description

[optional body]

[optional footer]
```

### مثال‌های عملی
```bash
# Features
feat(payments): add PayPal integration support
feat(admin): implement bulk user management interface

# Bug fixes  
fix(checkout): resolve tax calculation error for EU customers
hotfix(security): patch XSS vulnerability in comments

# Performance
perf(database): optimize user query with proper indexing
perf(assets): implement lazy loading for images

# Refactoring
refactor(core): restructure plugin initialization process
refactor(admin): extract settings page logic to separate class

# Documentation
docs(api): add comprehensive hook documentation
docs(readme): update installation requirements

# Tests
test(payments): add unit tests for payment gateway
test(integration): add E2E tests for checkout process
```

## 🚦 Branch Strategy

### ساختار Branch
```
main                 # Production ready code
├── develop          # Integration branch  
├── feature/*        # New features
├── bugfix/*         # Bug fixes
├── hotfix/*         # Critical fixes
├── refactor/*       # Code restructuring
├── perf/*           # Performance improvements
└── release/*        # Release preparation
```

### قوانین Merge
- تمام branch ها باید از `develop` branch کنده شوند
- Hotfix ها می‌توانند مستقیماً از `main` کنده شوند
- هر Pull Request باید حداقل یک review داشته باشد
- تست‌های خودکار باید قبل از merge پاس شوند

## ⚡ بهینه‌سازی عملکرد

### توصیه‌های کلیدی
1. **استفاده از WordPress Transient API** برای کش کردن داده‌ها
2. **بهینه‌سازی Database Queries** با استفاده از WP_Query
3. **Lazy Loading** برای Assets و منابع
4. **Minification** فایل‌های CSS و JavaScript
5. **استفاده از WordPress Hook System** به صورت بهینه

### مثال کد بهینه‌سازی
```php
// استفاده از Transient برای کش
function get_cached_user_stats($user_id) {{
    $cache_key = 'user_stats_' . $user_id;
    $stats = get_transient($cache_key);
    
    if (false === $stats) {{
        $stats = calculate_user_stats($user_id);
        set_transient($cache_key, $stats, HOUR_IN_SECONDS);
    }}
    
    return $stats;
}}
```

## 🛡️ نکات امنیتی

### اصول امنیتی اساسی
- **Data Sanitization**: همیشه داده‌های ورودی را تمیز کنید
- **Output Escaping**: خروجی‌ها را escape کنید  
- **Capability Checks**: دسترسی‌ها را بررسی کنید
- **Nonce Verification**: فرم‌ها را با nonce محافظت کنید
- **SQL Injection Prevention**: از prepared statements استفاده کنید

### مثال کد امن
```php
// تمیز کردن و اعتبارسنجی داده
$user_input = sanitize_text_field($_POST['user_data']);

// بررسی capability
if (!current_user_can('manage_options')) {{
    wp_die(__('Insufficient permissions.'));
}}

// اعتبارسنجی nonce
if (!wp_verify_nonce($_POST['nonce'], 'my_action')) {{
    wp_die(__('Security check failed.'));
}}
```

## 📊 معیارهای کیفیت

### KPI های کلیدی
- **Code Coverage**: حداقل 80% برای کد جدید
- **Performance Budget**: صفحات باید در کمتر از 3 ثانیه لود شوند
- **Memory Usage**: حداکثر 32MB برای یک request
- **Database Queries**: حداکثر 10 query در هر صفحه
- **Security Score**: بدون vulnerability در اسکن‌های امنیتی

## 🔄 فرآیند Review

### Checklist برای Code Review
- [ ] کد با WordPress Coding Standards مطابقت دارد
- [ ] Security best practices رعایت شده
- [ ] Performance implications بررسی شده  
- [ ] Tests نوشته شده یا آپدیت شده
- [ ] Documentation آپدیت شده
- [ ] Backward compatibility حفظ شده
- [ ] Patch Guard limits رعایت شده

---

**نسخه:** 1.0 · **Last Sync with SmartAlloc Docs:** 2025-09-07

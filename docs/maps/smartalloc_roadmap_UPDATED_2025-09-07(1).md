# SmartAlloc Roadmap — بروزرسانی ۱۶ شهریور ۱۴۰۴ (2025-09-07)

> **Note:** Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` (feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350; security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).
## وضعیت فعلی در برابر مبنا (Baseline)
- **Phase:** Foundation
- **پیشرفت فعلی:** ≈ ۶۸٪ (هدف مبنا: ۸۰٪)
- **شکاف‌های بحرانی:** RuleEngine (ترکیبی AND/OR/نگهبان عمق/اپراتور نامعتبر، تکمیل تست‌های منفی)، NotificationService (throttling پویا + شاخص‌های DLQ)، احتمال double-processing در GF (مسیر واحد سابمیشن انتخاب شود).
- **فرصت پیشرو:** Site Health: Plugin Health + Wrapperهای افزونه (PluginGuard/GFBridge/ASBridge).

## سیاست ادغام افزونه‌ها (Plugin Integration Policy)
- **Forms:** Gravity Forms (قطعی؛ منبع حقیقت برای جمع‌آوری داده‌ها).
- **Queue/Async:** Action Scheduler (Must-Have) — گروه `smartalloc`، backoff نمایی ۱m→۵m→۱۵m→۳۰m، حداکثر ۴ تلاش، DLQ فعال.
- **Mentor Populate (Field #39):** حالت **Hybrid** — اگر **GPPA** نصب باشد: Live/AJAX filters؛ اگر نباشد: **Fallback داخلی** با هوک‌های GF.
- **Email:** SMTP فقط در Prod — در Dev لاگ ایمیل با ماسک PII.
- **Rule-of-One:** WP Webhooks/Uncanny Automator فعلاً نصب نشود (Webhook داخلی کفایت می‌کند؛ در صورت نیاز واقعی یکی انتخاب می‌شود).
- **UTC Invariant:** همهٔ timestampها با UTC ذخیره شوند (`current_time('mysql', true)`/`gmdate()`)، تبدیل به منطقهٔ محلی فقط در UI.

## اولویت‌های اسپرینت بعدی (Baseline-Aligned)
1. **RuleEngine Composite** — نگهبان عمق (maxDepth=4)، جدول حقیقت AND/OR، تست‌های منفی (اپراتور نامعتبر/عمق زیاد).
2. **Notification Throttling + DLQ metrics** — تنظیمات پویا + شمارنده‌های `notify.rate_limited` و `dlq.size`، بازاجرایی امن.
3. **Action Scheduler Switch** — پیش‌فرض روی AS؛ WP-Cron فقط fallback. تعریف Groupها: `smartalloc_allocate`, `smartalloc_export`, `smartalloc_notify`.
4. **SchemaChecker واقعی برای GF** — راستی‌آزمایی وجود/نوع فیلدهای کلیدی (IDهای حیاتی).

## KPI و آستانه‌ها
- Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` (feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350; security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).
- **Queue:** Failed < ۱٪، p95 انتظار صف < ۱۰s، DLQ ≤ ۵.
- **Allocation:** p95 < ۸۰ms، انحراف ظرفیت ≤ ۵٪، تعارض منتور = ۰.
- **Export:** اوج حافظه < ۱۲۸MB، زمان ساخت ≤ ۲s (@N≈1000)، CSV-Injection = ۰.
- **Comms:** تحویل موفق ≥ ۹۸٪، Duplicate = ۰.
- **Forms:** خطای سابمیشن < ۰٫۵٪، سازگاری اسکیمای GF = ۱۰۰٪.

## نقاط تصمیم (Decisions)
- Constraint: Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` (feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350; security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).
- **تصمیم ۱:** تثبیت استفاده از Gravity Forms به‌عنوان زیرساخت فرم (بدون تغییر در مسیر فعلی).
- **تصمیم ۲:** اضافه‌کردن Action Scheduler به‌عنوان صف استاندارد و مهاجرت آداپتر فعلی؛ WP-Cron فقط به‌عنوان fallback.
- **تصمیم ۳:** Mentor Field #39 به‌صورت Hybrid — فعال‌سازی GPPA در صورت نیاز به فیلتر/جست‌وجوی زنده، همراه با Wrapper و Fallback داخلی.
- **تصمیم ۴:** SMTP فقط در Prod؛ در Dev صرفاً Mail Log با ماسک PII.
- **تصمیم ۵:** اعمال Rule-of-One: افزونه‌های اتوماسیون (WP Webhooks/Automator) نصب نشود مگر با توجیه روشن.
- **تصمیم ۶:** سفت‌کردن UTC در همهٔ ذخیره‌سازی‌ها و نمایش محلی در UI.
- **تصمیم ۷:** تکمیل Site Health (Plugin Health Dashboard) و افزودن KPIها.

## چک‌پوینت گذار فاز
- **Gate:** All PRs must PASS Patch Guard (branch-type caps) before merge.
- همهٔ آیتم‌های Foundation بحرانی بسته شوند (RuleEngine Composite، Throttling/DLQ metrics، مسیر واحد GF).
- **Security ≥ ۲۲**، **Performance ≥ ۱۹**، امتیاز وزنی ≥ ۹۵٪.
- عدم رگرسیون در Export/Allocation/Notifications.

---

## ضمیمه: نسخهٔ قبلی (برای مرجع)
(متن نسخهٔ قبلی در ادامه آمده است.)



# نقشه راه اجرایی SmartAlloc
## ۴ فاز کدنویسی برای تکمیل پروژه

> **وضعیت فعلی**: Foundation Phase (85% تکمیل شده)  
> **هدف**: رسیدن به Production-Ready WordPress Plugin  
> **زمان کل**: ۶-۸ روز کاری با پیروی دقیق از این roadmap

---

## 🎯 **فاز ۱: Critical Foundation (۲ روز)**
*هدف: رفع مسائل حیاتی که پروژه را مسدود می‌کنند*

### Day 1: NotificationService + Security Hardening

#### صبح (۴ ساعت):
```php
// 1. پیاده‌سازی throttling mechanism
class NotificationService {
    private function throttle(string $key, int $limitPerMin): void {
        $count = (int) get_transient($key) ?: 0;
        if ($count >= $limitPerMin) {
            $this->metrics->inc('notify_throttle_hits_total');
            throw new \RuntimeException('Throttle limit reached');
        }
        set_transient($key, $count + 1, 60);
    }
    
    // 2. اصلاح handle() برای پشتیبانی از DLQ
    public function handle(array $payload): void {
        $this->throttle('notify_' . gmdate('YmdHi'), SMARTALLOC_NOTIFY_RATE);
        // باقی کد...
    }
}
```

#### بعدازظهر (۴ ساعت):
- **DLQ Metrics Integration**: افزودن شمارنده‌های `notification.dlq` و `notify_*`
- **تست نویسی**: `NotificationThrottleTest`, `NotificationDlqMonitoringTest`
- **Security Review**: بررسی همه استفاده‌های `$wpdb->query()` و تبدیل به `DbSafe::mustPrepare()`

### Day 2: CircuitBreaker Typing + REST Security

#### صبح (۴ ساعت):
```php
// Typed DTO برای CircuitBreaker
class CircuitBreakerStatus {
    public function __construct(
        public bool $isOpen,
        public ?int $failureCount,
        public ?int $openedAt,
        public string $serviceName
    ) {}
}

// حذف phpcs:ignoreFile و استانداردسازی کد
```

#### بعدازظهر (۴ ساعت):
- **REST Endpoint Security**: افزودن `rest_validate_value_from_schema` به همه endpoints
- **Input Sanitization**: پیاده‌سازی `Redactor` class برای حذف PII از logs
- **تست Integration**: `CircuitBreakerStatusTypeTest`

---

## 🔧 **فاز ۲: Core Logic Enhancement (۲ روز)**
*هدف: تکمیل منطق کسب‌وکار و Rule Engine*

### Day 3: Rule Engine Composite Logic

#### صبح (۴ ساعت):
```php
// پیاده‌سازی AND/OR support
class RuleEngineService {
    private function evaluateNode(array $node, array $ctx): bool {
        if (isset($node['op'])) {
            $results = array_map(fn($r) => $this->evaluateNode($r, $ctx), $node['rules']);
            return $node['op'] === 'AND' ? 
                !in_array(false, $results, true) : 
                in_array(true, $results, true);
        }
        return $this->compare($ctx[$node['field']] ?? null, $node['value'], $node['operator']);
    }
}
```

#### بعدازظهر (۴ ساعت):
- **Logic Tree Storage**: افزودن `setLogicTree()` به `EvaluationResult`
- **تست‌های جامع**: `RuleEngineAndOrTest`, `RuleEngineCompositeAndTest`

### Day 4: Export Service Streaming + Performance

#### صبح (۴ ساعت):
```php
// Memory-efficient Excel export
private function writeXlsx(iterable $rows, string $file): void {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $rowIdx = 1;
    
    foreach ($rows as $row) {
        // نوشتن row
        $rowIdx++;
        
        // Memory management برای فایل‌های بزرگ
        if ($rowIdx % 500 === 0) {
            $sheet->garbageCollect();
        }
    }
}
```

#### بعدازظهر (۴ ساعت):
- **Bulk Insert Implementation**: `bulkInsertErrors()` با `DbSafe::mustPrepare`
- **Performance Testing**: تست با ۱۰۰۰ رکورد، هدف p95 < 2s
- **Normalization Rules**: حفظ leading zeros و validation rules

---

## 📊 **فاز ۳: Observability & Monitoring (۱.۵ روز)**
*هدف: مانیتورینگ کامل و debugging capability*

### Day 5: Logging Infrastructure

#### صبح (۴ ساعت):
```php
// Structured Logger با Correlation ID
class StructuredLogger implements LoggerInterface {
    private string $correlationId;
    
    public function log(string $level, string $message, array $context = []): void {
        $context = array_merge([
            'correlation_id' => $this->correlationId,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ], $context);
        
        $context = Redactor::sanitize($context);
        error_log(sprintf("[%s] %s: %s %s", $level, $this->correlationId, $message, json_encode($context)));
    }
}
```

#### بعدازظهر (۴ ساعت):
- **Tracer Implementation**: `startSpan()` و `finish()` در تمام services
- **Health Endpoint Extension**: افزودن Circuit Breaker و DLQ status
- **Correlation ID Propagation**: تزریق در همه service calls

### Day 6 (نصف روز): Cache & Final Integration

#### صبح (۴ ساعت):
- **CrosswalkService Versioning**: پیاده‌سازی cache invalidation با versioning
- **Integration Testing**: تست end-to-end با correlation IDs
- **Metrics Endpoint**: تکمیل `/metrics` با تمام counters

---

## 🚀 **فاز ۴: Production Readiness (۱.۵ روز)**
*هدف: آماده‌سازی برای production deployment*

### Day 7: Testing & Documentation

#### صبح (۴ ساعت):
```php
// Performance benchmarks
class ExportPerformanceTest extends WP_UnitTestCase {
    public function test_large_dataset_memory_usage() {
        $startMemory = memory_get_usage(true);
        
        $service = new ExportService();
        $service->generateExcel(range(1, 10000)); // 10k records
        
        $peakMemory = memory_get_peak_usage(true);
        $this->assertLessThan(128 * 1024 * 1024, $peakMemory); // < 128MB
    }
}
```

#### بعدازظهر (۴ ساعت):
- **Final Test Suite**: اجرای تمام تست‌ها با پوشش ≥ 85%
- **PHPCS Compliance**: حذف همه `phpcs:ignore` و تطابق کامل
- **Code Documentation**: تکمیل PHPDoc برای همه public methods

### Day 8 (نصف روز): Polish & Deployment Prep

#### صبح (۴ ساعت):
- **Error Handling Review**: بررسی همه exception handlers
- **Configuration Validation**: تست با تمام environment configs
- **Final Security Audit**: بررسی مجدد SQL queries و input validation

---

## ✅ **Checkpoint های کنترل کیفیت**

### بعد از هر روز:
1. **اجرای test suite کامل** - باید 100% pass شود
2. **بررسی memory usage** - هیچ memory leak نداشته باشد
3. **PHPCS check** - صفر warning/error
4. **Security review** - تمام user inputs sanitize شده باشند

### بعد از هر فاز:
1. **Integration testing** با داده‌های واقعی
2. **Performance benchmarking** 
3. **Code coverage report** (هدف: ≥ 85%)
4. **Documentation update**

---

## ⚠️ **نکات حیاتی برای موفقیت**

### 1. **Time Management**:
- هر task دقیقاً ۴ ساعت - اگر بیشتر کشید، به task بعدی بروید
- روزانه حداکثر ۸ ساعت کد زدن
- هر ۲ ساعت ۱۵ دقیقه استراحت

### 2. **Code Quality Gates**:
```bash
# هر commit باید این checks را pass کند:
composer phpcs
composer test
composer security-check
```

### 3. **Risk Mitigation**:
- همیشه branch جدید برای هر feature
- commit های کوچک و atomic
- backup روزانه از database

### 4. **Dependencies Management**:
- فقط از کتابخانه‌های approved استفاده کنید
- version lock همه dependencies
- تست backward compatibility با WP 6.0+

---

## 🎯 **Success Metrics**

### Technical KPIs:
- **Test Coverage**: ≥ 85%
- **Performance**: Export p95 < 2s برای 1000 records
- **Memory Usage**: < 128MB برای 10k records
- **Security**: Zero SQL injection vulnerabilities
- **PHPCS**: Zero violations

### Business KPIs:
- **Auto-allocation accuracy**: ≥ 95%
- **System uptime**: ≥ 99.9%
- **Error rate**: < 0.1%
- **Response time**: < 500ms for API calls

---

## 📋 **Daily Checklist Template**

```markdown
### Day X Checklist:
- [ ] Morning standup (5min) - plan day
- [ ] Code implementation (4h)
- [ ] Unit tests written (included in 4h)
- [ ] Integration test (4h afternoon)
- [ ] Code review self-check
- [ ] PHPCS compliance check
- [ ] Git commit with clear message
- [ ] Update progress in project tracker
- [ ] Evening review (5min) - plan tomorrow
```

---

## 🚦 **فاز بندی براساس اولویت**

| فاز | وضعیت | تاثیر بر پروژه | ریسک عدم انجام |
|-----|--------|----------------|-----------------|
| **Faz 1** | 🔴 Critical | پروژه مسدود | بالا |
| **Faz 2** | 🟡 High | کیفیت محصول | متوسط |
| **Faz 3** | 🟢 Medium | قابلیت نگهداری | پایین |
| **Faz 4** | 🔵 Nice-to-have | تجربه کاربری | خیلی پایین |

هر فاز را به ترتیب اولویت انجام دهید و **هیچ فازی را رد نکنید** تا به پروداکت نهایی با کیفیت برسید.





بر اساس تحلیل دقیق پروژه SmartAlloc شما، یک **نقشه راه مرحله‌بندی و اولویت‌دار** ارائه می‌دهم که بر تجربه 30 ساله در توسعه افزونه‌های وردپرس استوار است:بر اساس تجربه کاری، **چند نکته بحرانی** به شما می‌دهم که باعث موفقیت یا شکست این پروژه خواهند شد:

## 🎯 **راهبرد اجرایی موفق**

### **1. شروع با Critical Path**

همانطور که در گزارش مشخص است، شما **6.5 روز عقب** هستید. بجای پراکندگی، روی **NotificationService** تمرکز کنید چون:

- تمام Integration Tests بدون آن fail می‌کنند
- بقیه سرویس‌ها به DLQ metrics وابسته‌اند
- Security vulnerability بزرگی محسوب می‌شود

### **2. Time-Boxing سخت**

هر task دقیقاً **4 ساعت** زمان دارد. اگر کامل نشد، باید **MVP** آن بخش را تکمیل کنید و بروید جلو. تجربه نشان داده که perfectionism در این مرحله، killer است.

### **3. Test-First Development**

قبل از نوشتن کد، ابتدا test بنویسید. برای مثال:

```php
public function test_notification_throttle_prevents_flood() {
    // نخست این تست را بنویسید
    // سپس کد را implement کنید تا تست pass شود
}
```

## ⚠️ **خطرات احتمالی**

### **Scope Creep**

سند شما نشان می‌دهد تمایل به over-engineering. در فاز Foundation، فقط **minimum viable features** را پیاده‌سازی کنید. Enhancement ها بعداً.

### **Testing Debt**

85% پیشرفت ولی تست‌های حیاتی incomplete هستند. این یعنی regression risk بالا. حتماً موازی با کد، تست بنویسید.

## 💡 **پیشنهادات عملی**

### **روز اول**:

فقط روی NotificationService کار کنید. باقی چیزها را نادیده بگیرید. هدف: throttling + DLQ metrics کار کند.

### **کنترل روزانه**:

هر شب این سوالات را از خودتان بپرسید:

- آیا امروز حداقل یک critical gap بسته شد؟
- آیا تست‌های جدید pass می‌کنند؟
- آیا memory usage در حد مطلوب است؟

### **Integration Strategy**:

بجای Big Bang deployment، هر سرویس را جداگانه تست کنید با real data. مخصوصاً ExportService با 1000+ records.

**نکته نهایی**: بر اساس 30 سال تجربه، پروژه‌هایی که به timeline خودشان پایبند بمانند، معمولاً موفق می‌شوند. فاز‌بندی بالا realistic است اگر discipline را رعایت کنید.

موفق باشید! 🚀

> **Reference:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` — canonical rules, exceptions and tooling.
# Architecture & Governance Master (AGM) — SmartAlloc  
**نسخه:** v1.0.0 · **تاریخ بروزرسانی:** ۱۶ شهریور ۱۴۰۴ (2025-09-07)  
> **Note:** Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` (feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350; security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).
**Owner:** Principal Architect · **Reviewers:** PM, Security Lead, Data Owner · **Cadence:** ماهانه یا هنگام هر تصمیم کلیدی

> هدف: یک منبع واحد برای تصمیمات معماری (ADR)، حاکمیت فنی/امنیتی/داده، ریسک‌ها و نقش‌ها؛ هم‌راستا با Baseline (۱۰ شهریور ۱۴۰۴) و سیاست ادغام افزونه‌ها.

---

## 0) چشم‌انداز و اصول
- حفظ **مزیت رقابتی هسته**: Rule Engine، Allocation، Export، DLQ در کد داخلی باقی می‌مانند.
- استفاده از **افزونه‌های بالغ** برای زیرساخت commodity (GF, AS, SMTP، GPPA اختیاری) با **Wrapper/Fallback**.
Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07` for full rules (hotfix, bugfix, feature, refactor, perf, security, docs, tests, i18n, migration, compatibility).
- **استانداردهای WP:** prepared SQL، capability checks، nonce، HMAC، **UTC** برای ذخیره‌سازی.
- **KPI همسان** با Roadmap/Execution (Queue/Allocation/Export/Comms/Forms).

---

## 1) ADR Log (دفترچه تصمیمات معماری)
> قالب ADR زیر را برای هر تصمیم آینده کپی کنید. سپس ADRهای نمونهٔ اتخاذشده را می‌بینید.

### 1.1 قالب ADR (Template)
```
ADR-XXX: <عنوان کوتاه تصمیم>
Status: Accepted | Superseded | Deprecated
Date: YYYY-MM-DD
Context:
  - مسئله/نیاز
  - محدودیت‌ها/معیارهای انتخاب
Options:
  - گزینه A (مزایا/معایب)
  - گزینه B (مزایا/معایب)
Decision:
  - تصمیم نهایی + دامنه
Consequences:
  - پیامدهای فنی/سازمانی/ریسک‌ها
Owners: <مالک تصمیم> · Review: <نظارتی>
Links: <به اسناد Roadmap/Execution/Addendum/Code PR>
```

### 1.2 ADRهای مصوب (نمونه‌ها)
**ADR-001: استفاده از Gravity Forms به‌عنوان منبع حقیقت فرم‌ها**  
Status: Accepted · Date: 2025-09-07  
Context: نیاز به فرم پایدار، i18n/RTL، ایمپورت/اکسپورت، Hookهای غنی.  
Options: (A) GF، (B) فرم اختصاصی.  
Decision: GF انتخاب شد؛ ژنراتور JSON داخلی نگه داشته می‌شود.  
Consequences: هزینهٔ نگه‌داری کمتر؛ قفل به فروشنده با **GFBridge/Fallback** مهار می‌شود.

**ADR-002: Action Scheduler به‌عنوان صف پیش‌فرض**  
Status: Accepted · Date: 2025-09-07  
Context: صف پس‌زمینه با DLQ/Retry/Visibility نیاز است.  
Options: (A) AS، (B) WP-Cron + کد سفارشی.  
Decision: AS پیش‌فرض؛ WP-Cron فقط fallback. Groupها: `smartalloc_allocate/export/notify`.  
Consequences: مانیتورینگ بهتر؛ نیاز به نگهداری حداقلی.

**ADR-003: Mentor Field #39 — حالت Hybrid با GPPA (اختیاری)**  
Status: Accepted · Date: 2025-09-07  
Context: نیاز به فهرست پویا/فیلتر زنده در برخی سناریوها.  
Options: (A) داخلی صرف، (B) GPPA، (C) Hybrid + Wrapper.  
Decision: Hybrid (GPPA اگر موجود؛ در غیر اینصورت Fallback داخلی).  
Consequences: UX بهتر در دادهٔ حجیم؛ Vendor lock مهار با Wrapper.

**ADR-004: SMTP فقط در Prod؛ Dev صرفاً Mail Log**  
Status: Accepted · Date: 2025-09-07  
Context: تحویل‌پذیری ایمیل در Prod و جلوگیری از نشت PII در Dev.  
Options: (A) SMTP همه‌جا، (B) Prod-only، (C) بدون SMTP.  
Decision: Prod-only (WP Mail SMTP)، Dev=Mail Log + ماسک PII.  
Consequences: ایمیل قابل اتکا؛ ریسک کم برای Dev.

**ADR-005: Rule-of-One — عدم نصب WP Webhooks/Automator مگر با توجیه**  
Status: Accepted · Date: 2025-09-07  
Context: ازدیاد اتوماسیون‌های no-code باعث پیچیدگی/ریسک می‌شود.  
Options: (A) چند افزونه، (B) یکی، (C) فعلاً هیچ.  
Decision: فعلاً هیچ؛ وب‌هوک داخلی کافی است.  
Consequences: سادگی و امنیت بیشتر؛ در صورت نیاز آینده، انتخاب واحد.

**ADR-006: UTC سخت برای ذخیره‌سازی و نمایش محلی در UI**  
Status: Accepted · Date: 2025-09-07  
Decision: استفاده از `current_time('mysql', true)`/`gmdate()`؛ هر استفادهٔ non-UTC پرچم Site Health.

**ADR-007: مسیر واحد GF Submission (per-form registry)**  
Status: Accepted · Date: 2025-09-07  
Decision: فقط `gform_after_submission_{formId}` فعال؛ مسیر generic Deprecated.

**ADR-008: SchemaChecker واقعی برای فیلدهای حیاتی GF**  
Status: Accepted · Date: 2025-09-07  
Decision: بررسی وجود/نوع فیلدهای 39/20/92/93/94/75؛ نمایش Compatibility در UI.

---

## 2) Risk Register (ریسک‌لاگ رسمی)
| ID | ریسک | احتمال | اثر | امتیاز | مالک | اقدام کاهش | موعد بازبینی |
|----|------|--------|-----|--------|------|-------------|--------------|
| R-01 | RuleEngine Composite ناقص (عمق/اپراتور) | بالا | بالا | High | Core | تکمیل evaluateCompositeRule + تست منفی | هفتگی |
| R-02 | نبود throttling پویا/متریک‌های DLQ | متوسط | متوسط | Med | Core/Comms | افزودن کانترها + تنظیمات پویا + Replay امن | هفتگی |
| R-03 | Double-processing در GF | متوسط | بالا | High | Core | مسیر واحد per-form + حذف generic | فوری |
| R-04 | UTC ناسازگار در ذخیره‌سازی | متوسط | متوسط | Med | Sec/Core | Site Health پرچم + اصلاح فراخوانی‌ها | دوهفتگی |
| R-05 | Vendor lock-in (GPPA/AS) | پایین | متوسط | Low/Med | Arch | Wrapper/Fallback + تست CI «با/بی افزونه» | ماهانه |
| R-06 | OOM/Latency در Export | متوسط | متوسط | Med | Core/QA | Streaming/Chunking + بودجه حافظه | اسپرینتی |
| R-07 | شکست SMTP/تحویل ایمیل | پایین | بالا | Med | Ops/Sec | مانیتورینگ، SPF/DKIM/DMARC، Mail Log Dev | ماهانه |

---

## 3) Threat Model & Security Checklist (STRIDE)
**DFD (خلاصه):** کاربر ← GF Form (HTTP/HTTPS) ← WP Backend → Rule/Allocation → Exporter → SMTP/Webhook/Storage  
**دارایی‌ها:** داده‌های شخصی (PII)، نتایج تخصیص، فایل‌های Export، کلیدهای HMAC/SMTP.  
**تهدیدها و کنترل‌ها (نمونه):**
- **Spoofing:** احراز هویت نقش‌ها (`current_user_can`)، HMAC برای وب‌هوک، allowlist مقصدها.
- **Tampering:** nonce + `check_admin_referer`، prepared SQL، امضای پیام.
- **Repudiation:** لاگ ساخت‌یافته با ماسک PII، شناسه‌های ردیابی entry/job.
- **Information Disclosure:** حداقل‌گرایی داده، رمزنگاری در مسیر (TLS)، حذف ستون‌های حساس در Export.
- **Denial of Service:** Throttling اعلان/وب‌هوک، backoff نمایی صف، اندازه‌گیری p95 صف.
- **Elevation of Privilege:** حداقل Capability، جداسازی نقش‌ها، Site Health گیت امنیتی.

**چک‌لیست امنیت:** prepared SQL · nonce/cap · HMAC · UTC · PII Masking · حداقل دسترسی · اسکن CSV/Excel فرمول · لاگ‌های قابل ممیزی.

---

## 4) Data Governance & PII Policy
- **طبقه‌بندی:** PII (بالا)، دادهٔ عملیاتی (متوسط)، لاگ‌ها (پایین با ماسک).  
- **Retention:** فرم/ورودی‌ها طبق سیاست سازمان؛ لاگ‌ها با ماسک و نگهداری محدود.  
- **دسترسی:** اصل «کمترین دسترسی»؛ همهٔ عملیات حساس با `manage_smartalloc`.  
- **درخواست‌های کاربر (DSAR):** export/delete داده به‌درخواست با ممیزی.  
- **ماسک PII:** ایمیل/موبایل در لاگ‌ها؛ ارسال در Dev فقط شبیه‌سازی‌شده.  
- **اشتراک‌گذاری:** فقط از مسیرهای مجاز (SMTP/Webhook با HMAC/allowlist).

---

## 5) Vendor/Plugin Lifecycle (طرف‌سوم)
- **ارزیابی پذیرش:** بلوغ، سازگاری نسخه، امنیت، پرفورمنس.  
- **Version Lock:** قفل نسخه در Staging؛ انتشار کنترل‌شده به Prod.  
- **CVE Monitoring:** پیگیری بولتن‌ها؛ وصلهٔ امنیتی فوری.  
- **Upgrade Plan:** Staging → تست KPI → Prod.  
- **Exit Plan:** Wrapper/Fallback آماده؛ **عدم انتقال منطق هسته** به افزونهٔ ثالث.

---

## 6) Stakeholders, RACI & Comms
- **Stakeholders:** PM، Core، QA، Ops/SRE، Security، Data Owner.  
- **RACI (نمونه):**
  - Rule/Allocation/Export: **R/A**=Core · **C**=QA · **I**=PM
  - Queue/AS & Plugin Health: **R**=Core · **A**=PM · **C**=Ops
  - SMTP/Prod: **R**=Ops · **A**=Sec · **C**=PM
  - Site Health: **R**=Core · **A**=QA · **C**=Ops
- **Comms Plan:** کانال‌های Slack/Email، الگوهای Incident/Release، پنجرهٔ انتشار، Go/No-Go.

---

## 7) Versioning & Change Control
- SemVer برای سند (vMAJOR.MINOR.PATCH).
- PR الزامی برای هر تغییر؛ پیوست ADR/ریسک/تأثیر KPI.
- Audit Trail: ثبت تاریخچهٔ تغییرات در انتهای فایل.

---

## 8) پیوست‌ها
- لینک به: Roadmap/Execution/Baseline/Addendum/Config Notes/Admin Guide/Site Health.
- **الگوها:** ADR Template (بالا)، Risk Register CSV Template، Incident Template.

---

## History (تاریخچه)
- v1.0.0 — 2025-09-07: ایجاد سند AGM بر اساس تصمیمات Build vs Plugin، امنیت/داده، ریسک‌ها و RACI.

---

## ضمیمه: نسخهٔ قبلی (برای مرجع)
(این **اولین نسخه** از AGM است و نسخهٔ قبلی برای درج وجود ندارد. در بروزرسانی‌های بعدی، متن نسخهٔ پیشین اینجا افزوده می‌شود.)


> **Reference:** See `PATCH_GUARD_POLICY_v2_2025-09-07.md` for full rules and CI enforcement.


> **Reference:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` — canonical rules, exceptions and tooling.
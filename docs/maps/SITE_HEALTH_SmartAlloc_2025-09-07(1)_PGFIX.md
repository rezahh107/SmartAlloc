# SmartAlloc — SITE HEALTH Tests (راهنمای سلامت سایت)  
**بروزرسانی:** ۱۶ شهریور ۱۴۰۴ (2025-09-07)
> **Note:** Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` (feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350; security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).

این سند منطق تست‌های Site Health سفارشی SmartAlloc را توضیح می‌دهد، KPI/آستانه‌ها را مشخص می‌کند و اقدام‌های اصلاحی را گام‌به‌گام ارائه می‌دهد.

---

## 1) معماری و نحوهٔ اجرا
- تست‌ها در **Tools → Site Health → Status** با عنوان **Plugin Health (SmartAlloc)** نمایش داده می‌شوند.
- خروجی‌ها سه سطح دارند: **Good (سبز)**، **Recommended (زرد)**، **Critical (قرمز)**.
- هر تست اقدام‌محور است (لینک به صفحهٔ مربوطه در ادمین).

---

## 2) فهرست تست‌ها و معیارها

### 2.1 Gravity Forms — Active & Version
- **چک‌ها:** فعال‌بودن GF، نسخه ≥ مینیمم پروژه؛ هم‌نسخه‌سازی **GFFormGenerator** با نسخهٔ GF سایت (ترجیح 2.8).
- **اقدام قرمز:** فعال‌سازی/به‌روزرسانی GF؛ ارتقای ژنراتور به 2.8 یا ثبت یادداشت سازگاری.

### 2.2 Action Scheduler — Active, Latency, Failures, Groups
- **چک‌ها:** فعال‌بودن AS؛ latency < **300ms**؛ Failed actions زیر آستانه؛ وجود Groupهای `smartalloc_*`؛ **WP-Cron** فقط fallback.
- **KPI:** Failed < **۱٪**، DLQ ≤ **۵**، p95 انتظار صف < **۱۰s**.
- **اقدام قرمز:** نصب/فعال‌سازی AS، ساخت Groupها، تنظیم Backoff (1,5,15,30)، migrate آداپتر به AS.

### 2.3 Rule-of-One — Automation Plugins
- **چک‌ها:** عدم نصب همزمان یا بدون توجیه **WP Webhooks/Uncanny Automator**.
- **اقدام زرد/قرمز:** حذف/غیرفعال‌سازی تا زمان نیاز واقعی مستند.

### 2.4 SMTP / Mail Log — Env-aware
- **چک‌ها:** در **Prod**: فعال و پیکربندی‌شده بودن **WP Mail SMTP**؛ در **Dev**: Mail Log فعال و **ماسک PII**.
- **اقدام قرمز:** تنظیم DNS/SMTP، غیرفعال‌سازی لاگ خام در Prod.

### 2.5 UTC Invariant
- **چک‌ها:** عدم استفاده از `current_time('mysql')` بدون `GMT=true`؛ استفادهٔ صحیح از `gmdate()`/UTC در ذخیره‌سازی.
- **اقدام قرمز:** اصلاح فراخوانی‌ها؛ تبدیل به محلی فقط در UI.

### 2.6 SchemaChecker — GF Required Fields
- **چک‌ها:** وجود/نوع فیلدهای حیاتی: 39, 20, 92, 93, 94, 75 …  
- **اقدام قرمز:** اصلاح فرم یا ایمپورت JSON صحیح؛ هماهنگ‌سازی با `SabtEntryMapper`.

### 2.7 GF Submission — Single Path Guard
- **چک‌ها:** فعال‌بودن **تنها یک** مسیر `gform_after_submission` برای فرم‌های Enabled.
- **اقدام قرمز:** غیرفعال‌سازی مسیر generic یا موازی؛ نگه‌داشتن مسیر رجیستری per-form.

### 2.8 Redis Object Cache (اختیاری)
- **چک‌ها:** وجود drop-in و فعال‌بودن؛ **Hit Rate ≥ 80%** (اگر متریک موجود).
- **اقدام زرد:** فعال‌سازی/بهینه‌سازی Redis؛ نام‌فضا دادن به کلیدهای SmartAlloc.

### 2.9 DLQ Size & Replay
- **چک‌ها:** اندازهٔ DLQ ≤ **۵**؛ قابلیت Replay از UI داخلی/AS.
- **اقدام قرمز:** بررسی خطاهای مداوم، افزایش Backoff، بهبود robustness اعلان/وب‌هوک.

### 2.10 Export Performance (Staging-only)
- **چک‌ها:** N≈1000 → p95 ساخت ≤ **۲s**, Peak Mem < **۱۲۸MB**؛ CSV-Injection = **۰**.
- **اقدام قرمز:** فعال‌سازی Streaming/Chunking، بازبینی Queryها/Memory limit.

---

## 3) نحوهٔ تفسیر خروجی
- **سبز (Good):** اقدام لازم نیست؛ پایش دوره‌ای کافی است.
- **زرد (Recommended):** اقدام پیشنهادی برای بهبود یا کاهش ریسک.
- **قرمز (Critical):** باید پیش از ادامهٔ توسعه/دیپلوی اصلاح شود.

---

## 4) اجرای تست‌ها و Runbook عملیاتی
1) به Tools → Site Health بروید و بخش **Plugin Health (SmartAlloc)** را باز کنید.  
2) موارد قرمز را ابتدا رفع کنید (AS/GF/UTC/Submission path).  
3) سپس موارد زرد (Redis/SMTP Dev/Rule-of-One) را بهبود دهید.  
4) دوباره تست‌ها را اجرا کنید؛ همه باید سبز شوند.  
5) KPIهای سطح سیستم را با داشبورد یا لاگ‌ها پایش کنید.

---

## 5) پیاده‌سازی (برای تیم توسعه)
- تست‌ها از طریق فیلتر `site_status_tests` ثبت می‌شوند و هر تست باید **پیام اقدام‌محور** و لینک به صفحهٔ مرتبط داشته باشد.
- خروجی‌ها باید **machine-readable** باشند تا در CI نیز مصرف شوند.

---

## 6) KPI/Threshold Summary (مرجع سریع)
- Queue: Failed < ۱٪ · DLQ ≤ ۵ · p95 انتظار < ۱۰s · Latency < 300ms  
- Allocation: p95 < ۸۰ms · Capacity dev ≤ ۵٪ · Conflict = ۰  
- Export: Peak < ۱۲۸MB · Build ≤ ۲s · CSV-Inj = ۰  
- Comms: Delivery ≥ ۹۸٪ · Duplicate = ۰  
- Forms: Submission error < ۰.۵٪ · Compatibility = ۱۰۰٪

---

## ضمیمه: نسخهٔ قبلی (برای مرجع)
(این **اولین نسخه** از SITE_HEALTH است و نسخهٔ قبلی برای درج وجود ندارد. در بروزرسانی‌های بعدی، متن نسخهٔ پیشین اینجا افزوده می‌شود.)


> **Reference:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` — canonical rules, exceptions and tooling.
## Patch Guard — Site Health Checks
**هدف:** ارزیابی پیوستگی سیاست Patch Guard در مخزن و چرخهٔ PR/Release.

### موارد بررسی (Good/Recommended/Critical)
- **Policy file present & up-to-date:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md`  → Good؛ اگر وجود ندارد → **Critical**
- **GH Actions workflow for Patch Guard موجود:** `.github/workflows/patch-guard.yml` → Recommended؛ اگر وجود ندارد → Warning
- **PR Gate فعال:** همهٔ PRها باید Patch Guard را پاس کنند (Branch-type caps) → Good؛ در صورت عدم الزام → **Critical**
- **آخرین ۲۰ PR:** تعداد نقض‌ها = ۰ → Good؛ ۱–۲ → Recommended؛ ≥۳ → **Critical**

### سیگنال‌ها (Site Health)
- **Status:** Good / Recommended / Critical
- **Details:** (آخرین اجرای CI، نسخهٔ Policy، شمارش نقض‌ها)

### اقدامات اصلاحی
- اضافه/به‌روزرسانی فایل: `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md`
- فعال‌سازی GitHub Action برای Patch Guard
- الزام Gate در Branch Protection / CI
- آموزش تیم برای استفاده از `scripts/patch-guard-check.sh`

> **Reference:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` — canonical rules, exceptions and tooling.

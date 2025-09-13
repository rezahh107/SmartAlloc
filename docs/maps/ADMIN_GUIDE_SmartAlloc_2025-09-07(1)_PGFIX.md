# SmartAlloc — ADMIN GUIDE (راهنمای ادمین)  
**بروزرسانی:** ۱۶ شهریور ۱۴۰۴ (2025-09-07)
> **Note:** Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` (feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350; security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).

این راهنما مخصوص مدیران سایت/عملیات است تا SmartAlloc را با **بهترین روش‌ها** راه‌اندازی، پایش و عیب‌یابی کنند — با تمرکز بر اینکه تیم **روی موتور هسته** (Rule / Allocation / Export / DLQ) متمرکز بماند.

---

## 1) نقش‌ها و پیش‌نیازها
- **WordPress 6.4+**, PHP 8.1+, MySQL 8+/MariaDB 10.5+
- **Capability الزامی:** `manage_smartalloc` برای Import/Allocate/Export و تنظیمات حساس.
- **پلاگین‌ها**
  - ضروری: **Gravity Forms**, **Action Scheduler**
  - Prod فقط: **WP Mail SMTP**
  - اختیاری: **GP Populate Anything** (Mentor#39 Live/AJAX)، **Members** (فقط UI نقش‌ها)، **Redis Object Cache** / **Object Cache Pro**
  - Dev-only: **Query Monitor**, **WP Crontrol**
- **سیاست‌ها (Policy)**
  - **Rule-of-One:** فعلاً **WP Webhooks/Uncanny Automator نصب نشوند** (وب‌هوک داخلی کفایت می‌کند).
  - **UTC سفت:** ذخیره‌سازی زمان‌ها به UTC؛ نمایش محلی فقط در UI.

---

## 2) نقشهٔ منوها
SmartAlloc (منوی ادمین) → **Forms**, Allocation, Export, Reports, Settings, Logs, Debug

---

## 3) مدیریت فرم‌ها (Forms)
### 3.1 ایمپورت/ساخت فرم
- گزینه A: از «**SmartAlloc → Forms → Generate GF JSON**» فایل JSON بسازید و در GF ایمپورت کنید.  
- گزینه B: فایل آمادهٔ `gf_form_import_SmartAlloc_v2.2.json` (هدف GF=2.8) را ایمپورت کنید.

> **یادداشت نسخه:** ژنراتور داخلی فعلی 2.5.x می‌سازد. توصیه می‌شود به 2.8 ارتقا یابد یا در Execution ذکر سازگاری نگهداری شود.

### 3.2 فعال‌سازی فرم هدف
- در «SmartAlloc → Forms» فرم(ها) را **Enable** کنید.  
- ستون **Compatibility** باید سبز باشد — این خروجی **SchemaChecker** است (فیلدهای حیاتی: 39/20/92/93/94/75).

### 3.3 مسیر واحد سابمیشن (جلوگیری از Double-Processing)
- فقط **یک مسیر** برای `gform_after_submission` فعال باشد: **رجیستری per-form**.  
- مسیر generic (سراسری) باید غیرفعال/Deprecated باشد.

---

## 4) Mentor Populate (Field #39)
- **بدون GPPA:** Populate سمت سرور با `gform_pre_render`/`gform_pre_validation` (بدون Live/AJAX).  
- **با GPPA (توصیه برای دادهٔ پویا/حجیم):** Live/AJAX + جست‌وجو/صفحه‌بندی/کش؛ فیلترها از فیلدهای 92/93/94/75.  
- **اصل:** سیاست ظرفیت/رتبه‌بندی در **AllocationEngine** می‌ماند؛ UI فقط راهنماست.

---

## 5) صف/پس‌زمینه (Action Scheduler)
- **Groups:** `smartalloc_allocate`, `smartalloc_export`, `smartalloc_notify`  
- **Backoff (Retry):** 1m → 5m → 15m → 30m (max 4)  
- **DLQ:** Failed Actions؛ Replay از UI AS یا صفحهٔ DLQ داخلی  
- **Idempotency:** استفاده از `as_schedule_unique_action` بر اساس `(entry_id, stage)`  
- **مانیتورینگ:** Tools → **Scheduled Actions**، فیلتر بر اساس Group یا Status  
- **KPI:** Failed < ۱٪، p95 انتظار < ۱۰s، DLQ ≤ ۵

---

## 6) Export
- «SmartAlloc → Export»: تولید فایل **SabtExport-ALLOCATED-YYYY_MM_DD-####-B{nnn}.xlsx**  
- **Sheets:** Summary, Errors  
- **ایمنی:** ضد CSV/Excel formula injection فعال (Escaping پیش‌فرض)  
- **بودجهٔ پرفورمنس:** N≈1000 → p95 ≤ ۲s، Peak Memory < ۱۲۸MB

---

## 7) اعلان‌ها/ایمیل
- **Prod:** تنظیم **WP Mail SMTP** (SPF/DKIM/DMARC معتبر)؛ لاگ خاموش یا ماسک PII  
- **Dev:** Mail Log فقط برای دیباگ، ارسال واقعی نداشته باشید  
- **KPI:** Delivery ≥ ۹۸٪، Duplicate = ۰، p95 ارسال < ۳۰۰ms

---

## 8) وب‌هوک‌ها
- **داخلی:** REST با امضای **HMAC** و **Replay-Guard ±5m**؛ Allowlist مقصدها  
- **Rule-of-One:** افزونه‌های اتوماسیون No-code را نصب نکنید مگر نیاز واقعی مستند شود.

---

## 9) Site Health و KPIها
- از «Tools → Site Health → **Plugin Health (SmartAlloc)**» گزارش سلامت را ببینید:  
  - GF/AS فعال + نسخه‌ها ≥ مینیمم  
  - صف AS سالم (Latency/Failed/DLQ)  
  - Rule-of-One رعایت شده  
  - SMTP (Prod)/Mail Log (Dev)  
  - UTC سخت و **عدم** استفاده از `current_time('mysql')` بدون GMT=true  
  - SchemaChecker (فیلدهای حیاتی)  
  - **Single-path** GF submission

---

## 10) عیب‌یابی سریع (Troubleshooting)
- **دوبار تخصیص**: مسیر generic GF را غیرفعال کنید و تنها per-form بگذارید.  
- **تاخیر صف**: گروه‌ها/Backoff را چک کنید؛ اگر WP-Cron فعال است، AS را پیش‌فرض کنید.  
- **عدم تحویل ایمیل**: DNS/SMTP، لاگ خطا، و ماسک PII را بررسی کنید.  
- **عدم سازگاری فرم**: خروجی SchemaChecker را ببینید؛ فیلدهای حیاتی را تطبیق دهید.  
- **کندی Admin**: Dev-only ابزارها را در Prod غیرفعال کنید؛ کش شیء (Redis) را فعال کنید.

---

## 11) CLIهای پرکاربرد
- ساخت فرم JSON: `wp smartalloc gf generate --output=/path/form.json`  
- تخصیص دسته‌ای: `wp smartalloc allocate --form=150 --dry-run`  
- تولید خروجی: `wp smartalloc export --form=150`

---

## 12) KPI و پذیرش (Acceptance)
- Queue: Failed < ۱٪، p95 انتظار < ۱۰s، DLQ ≤ ۵؛ Replay موفق ≥ ۹۹٪  
- Allocation: p95 < ۸۰ms، انحراف ظرفیت ≤ ۵٪، Mentor conflict = ۰  
- Export: Peak < ۱۲۸MB، Build ≤ ۲s، CSV-Injection = ۰  
- Forms: Submission error < ۰.۵٪، Compatibility = ۱۰۰٪  
- Comms: Delivery ≥ ۹۸٪، Duplicate = ۰

---

## 13) امنیت و حریم خصوصی
- **prepared SQL**، **nonce/cap**، **HMAC**، **ماسک PII**، **UTC**  
- دسترسی Import/Allocate/Export فقط برای نقش‌های مجاز (`manage_smartalloc`).

---

## 14) تغییرات نسخه
- این راهنما با **تصمیمات 2025-09-07** هم‌راستا شده است (GF تثبیت، AS پیش‌فرض، GPPA Hybrid، SMTP Prod، Rule-of-One، UTC، SchemaChecker).

---

## ضمیمه: نسخهٔ قبلی (برای مرجع)
(این **اولین نسخه** از ADMIN_GUIDE است و نسخهٔ قبلی برای درج وجود ندارد. در بروزرسانی‌های بعدی، متن نسخهٔ پیشین اینجا افزوده می‌شود.)


> **Reference:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` — canonical rules, exceptions and tooling.
## Admin PR & Release Checklist
- **Patch Guard Gate:** All PRs affecting Admin UI/Settings MUST PASS Patch Guard (branch-type caps) before merge.
- **Reference:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md`
- **CI Tip:** Use `scripts/patch-guard-check.sh` locally before opening the PR.

# Operations & Release Playbook (ORP) — SmartAlloc  
**نسخه:** v1.0.0 · **تاریخ بروزرسانی:** ۱۶ شهریور ۱۴۰۴ (2025-09-07)  
> **Note:** Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` (feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350; security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).
**Owner:** Ops/SRE Lead · **Reviewers:** PM, Dev Lead, Security Lead · **Cadence:** هر انتشار / پس از هر Incident

> هدف: راهنمای واحد برای **انتشار/بازگشت (Rollback)**، **Runbook عملیاتی و Incident Playbook**، **Site Health & Monitoring** و **Backup/DR**. کاملاً هم‌راستا با Baseline، KPIها و تصمیمات Build vs Plugin (GF/AS/SMTP/GPPA).

---

## 0) اصول عملیاتی
- **Focus on Core:** هیچ تغییری نباید منطق هسته (Rule/Allocation/Export/DLQ) را به افزونهٔ ثالث منتقل کند.
- **Feature Flags:** سوییچ‌های GPPA و Queue (AS/Cron) و اعلان‌ها باید از Settings کنترل شوند.
- **UTC:** ذخیره‌سازی زمان‌ها در UTC؛ نمایش محلی فقط در UI.
- **Quality Gates:** انتشار فقط با Site Health سبز و KPIها در آستانه مجاز است.
Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07` for full rules (hotfix, bugfix, feature, refactor, perf, security, docs, tests, i18n, migration, compatibility).

---

## 1) چرخه انتشار (Release Pipeline)

### 1.1 پیش‌نیازهای انتشار (Pre-Release Checklist)
- [ ] **Patch Guard Gate:** All PRs in this release **PASS** branch-type caps — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md`
- [ ] **Site Health (SmartAlloc)**: همه تست‌ها سبز؛ مخصوصاً GF/AS/UTC/Submission Path/SchemaChecker
- [ ] **GF/AS/SMTP نسخه‌ها** ≥ مینیمم پروژه؛ Rule-of-One رعایت شده
- [ ] **Action Scheduler**: گروه‌ها (`smartalloc_*`) حاضر؛ Backoff (1,5,15,30) اعمال
- [ ] **SchemaChecker**: Compatibility=۱۰۰٪ برای فرم‌های Enabled
- [ ] **CI/CD Quality Gates**: پوشش/تست‌ها/KPI پاس
- [ ] **Feature Flags**: مقدار هدف روشن (GPPA/Queue/Comms)
- [ ] **Backup Validity**: آخرین بکاپ موفق و آزمون بازیابی وجود دارد

### 1.2 مراحل انتشار (Staging → Prod)
1) **Staging Deploy**  
   - اجرای اسموک: ارسال فرم Sabt → Rule/Allocation → AS Job → اعلان/وب‌هوک → Export  
   - تایید KPIها (Queue/Allocation/Export/Comms/Forms)
2) **Canary Release (Prod)**  
   - فعال‌سازی برای یک فرم/بخش محدود (Feature Flag)  
   - پایش متریک‌ها: p95 صف/ارسال/Export و نرخ خطا
3) **Rollout کامل** در صورت سبز بودن Canary (Go/No-Go معیارها در بخش 5)

### 1.3 پس از انتشار (Post-Release)
- **Monitoring 24h**: آلارم‌ها روی KPIهای حیاتی فعال
- **Smoke مجدد**: نمونه واقعی/کنترل‌شده برای تایید مسیر کامل
- **Change Log**: ثبت تغییرات/نسخه در Admin/Repo

---

## 2) برنامه بازگشت (Rollback Plan)

### 2.1 محرک‌های Rollback (Triggers)
- KPI خارج از آستانه (Queue Failed>۱٪، p95 انتظار>۱۰s، Export OOM/>۲s @N≈1000، Delivery<۹۸٪)
- Site Health از سبز خارج شود (Critical)
- افزایش غیرعادی DLQ یا Duplicate ارسال
- خطاهای امنیتی (UTC/nonce/cap/HMAC نقض)

### 2.2 اقدام‌های Rollback (Sequence)
1) **Freeze** تغییرات جدید (Feature Flags: خاموش کردن GPPA/وب‌هوک خارجی/سوییچ صف → Cron یا بالعکس)  
2) **Revert Deploy** به نسخهٔ پایدار قبلی (با کش صفحه/شیء پاکسازی)
3) **Action Scheduler**  
   - توقف زمان‌بندی جدید برای گروه‌های `smartalloc_*` (feature flag)  
   - بررسی **Failed Actions (DLQ)** و جلوگیری از بازاجرایی کور
4) **Smoke بازگشت**: همان سناریوی انتشار، باید سبز شود
5) **اطلاع‌رسانی ذینفعان** (الگو در بخش 6)

### 2.3 ماتریس Rollback (نمونه‌ها)
| سناریو | اقدام فوری | اقدام تکمیلی |
|---|---|---|
| صف کند/Failed>۱٪ | Queue→Cron (یا محدودسازی)، کاهش نرخ ورودی، پاکسازی Locks | بررسی Jobهای مشکل‌دار، Backoff/Retry، replay بعد از Fix |
| SMTP قطع | Comms Off → DLQ فعال | سوئیچ به SMTP پشتیبان، تست DNS، ارسال مجدد |
| Dup. Processing | غیرفعال کردن مسیر generic GF | Hotfix: فقط registry per-form؛ پاکسازی موارد تکراری |
| Export OOM/کند | فعال‌سازی Streaming/Chunking | بهینه‌سازی Batch/Memory؛ پروفایلینگ |
| Webhook Tamper/Invalid | خاموش کردن ارسال بیرونی | بررسی HMAC/Clock/Secret/Allowlist؛ Replay امن |

---

## 3) Incident Playbooks (سناریو به سناریو)

### 3.1 صف/پس‌زمینه ازدحام (AS Backlog/Fail)
**Detect:** p95 انتظار>۱۰s یا Failed>۱٪ یا DLQ>۵  
**Mitigate فوری:**  
- محدودسازی ورودی (Feature Flag روی فرم‌های غیرحیاتی)  
- اجرای دستی پردازش‌ها:  
  ```bash
  wp action-scheduler run --group=smartalloc_allocate --batches=10
  wp action-scheduler run --group=smartalloc_notify --batches=10
  ```
- بررسی Locks/Transients قدیمی و حذف ایمن‌شان
**Diagnose:** گزارش Scheduled Actions → فیلتر بر اساس Status=Failed/Running؛ لاگ‌های ساخت‌یافته  
**Fix:** تنظیم Backoff، اصلاح Jobهای مشکل‌دار، Patch در Adapter/Bridges  
**Recover:** Replay DLQ (پس از Fix)، پایش KPI 24h، گزارش RCA

### 3.2 اختلال ایمیل/اعلان (SMTP/Comms)
**Detect:** Delivery<۹۸٪، نرخ Bounce>۱٪، خطای SMTP  
**Mitigate:** Comms Off (Feature Flag) → DLQ فعال؛ اطلاع‌رسانی داخلی  
**Diagnose:** DNS/SPF/DKIM/DMARC، محدودیت‌های ارائه‌دهنده، لاگ خطا  
**Fix:** سوییچ به سرویس پشتیبان/رفع پیکربندی  
**Recover:** Replay اعلان‌های DLQ، تایید KPI

### 3.3 دوباره‌پردازی سابمیشن (GF Double-Processing)
**Detect:** دو لاگ تخصیص برای یک Entry  
**Mitigate:** غیرفعال کردن مسیر generic GF (سراسری)  
**Fix:** فعال‌سازی فقط per-form registry و تست اسموک  
**Recover:** پاکسازی نتایج تکراری، پایش مجدد

### 3.4 Export کند/OOM
**Detect:** p95>۲s (@N≈1000) یا Peak>۱۲۸MB یا خطای OOM  
**Mitigate:** فعال‌سازی Streaming/Chunking و کاهش اندازه Batch  
**Diagnose:** پروفایلینگ مسیر Export، Queryها، دادهٔ ورودی  
**Fix:** بهینه‌سازی حافظه/IO، ایندکس‌ها (Staging فقط)  
**Recover:** اسموک Export، تایید KPI

### 3.5 Webhook Tamper/Clock Skew
**Detect:** HMAC invalid / Replay blocked بی‌دلیل  
**Mitigate:** خاموشی Webhook‌های بیرونی (Rule-of-One)  
**Diagnose:** زمان سیستم/UTC، چرخش secret، Allowlist مقصد  
**Fix:** هم‌زمان‌سازی ساعت، بازتولید secret، تصحیح window  
**Recover:** Replay امن و تایید امضا

---

## 4) Site Health & Monitoring
- **Signal:** Recent PR Patch Guard violations = 0 (alert if ≥1 in last 10 PRs).

### 4.1 Site Health (SmartAlloc)
- **Good/Recommended/Critical** با اقدامات اصلاحی لینک‌دار
- تست‌های کلیدی: GF/AS/UTC/SchemaChecker/Submission Path/SMTP/Rule-of-One/Redis/DLQ

### 4.2 KPIهای پایش و آلارم‌ها
- **Queue:** Failed>۱٪ · p95 انتظار>۱۰s · DLQ>۵ → Critical
- **Allocation:** p95>۸۰ms یا Conflict>۰ → Warning/Critical
- **Export:** Peak>۱۲۸MB یا p95>۲s → Warning/Critical
- **Comms:** Delivery<۹۸٪ یا Duplicate>۰ → Critical
- **Forms:** Submission error>۰.۵٪ یا Compatibility<۱۰۰٪ → Critical

### 4.3 داشبوردها و لاگ‌ها
- داشبورد KPI (Queue/Allocation/Export/Comms/Forms)
- لاگ‌های ساخت‌یافته (بدون PII) با شناسهٔ Entry/Job

---

## 5) Go/No-Go — معیار تصمیم
- **Go/No-Go:** If any PR **fails Patch Guard**, decision = **No-Go** until fixed and re-checked.
- Site Health: **سبز**
- KPIها: در آستانه (Queue/Allocation/Export/Comms/Forms)
- Smoke: مسیر کامل فرم → تخصیص → AS → اعلان/Webhook → Export **بدون خطا**
- ریسک‌های High: بدون اقدام بازدارنده **معلق** نباشد
- Backup: تایید صحت آخرین بازیابی

**No-Go** اگر یکی از موارد بالا نقض شود؛ Rollback طبق بخش 2.

---

## 6) برنامه ارتباطات و ذی‌نفعان (Comms & Stakeholders)
- **کانال‌ها:** Slack/Email/Status Page
- **الگوها:**
  - *Pre-Release Notice*: زمان‌بندی، ریسک‌ها، کانال پشتیبانی
  - *Go/No-Go Update*: نتیجهٔ اسموک/KPI، تصمیم
  - *Incident Update*: خلاصه مشکل، اثر، زمان بازیابی/اقدامات
  - *Postmortem*: RCA، اقدام‌های پیشگیرانه (ADR/Risk Register)
- **Escalation:** Ops → Dev Lead → Security → PM/Stakeholders

---

## 7) Backup & DR (پشتیبان‌گیری و بازیابی)
- **محدوده بکاپ:** DB کامل، `wp-content/uploads` (به‌خصوص خروجی‌ها)، `wp-config.php` (بدون secrets در نسخهٔ عمومی)، تنظیمات SmartAlloc
- **Cadence:** روزانه (DB)، هفتگی (Full)؛ Snapshots برحسب نیاز
- **Retention:** طبق سیاست سازمان (مثلاً ۳۰/۹۰ روز)
- **RPO/RTO پیشنهادی:** RPO ≤ ۴h · RTO ≤ ۲h
- **تست بازیابی:** ماهانه در محیط ایزوله؛ تایید Smoke بعد از بازیابی
- **DR Plan:** مستندسازی قدم‌به‌قدم بازیابی در منطقه یا سرور جایگزین

---

## 8) پیوست‌ها و ابزارها
- **wp-cli نمونه‌ها**
  ```bash
  # اجرای دسته‌ای AS
  wp action-scheduler run --group=smartalloc_export --batches=5
  # شمارش Failed Actions (DLQ)
  wp action-scheduler list --status=failed --per-page=20
  ```
- لینک به: Admin Guide, Site Health Guide, Execution Doc, Addendum

---

## History (تاریخچه)
- v1.0.0 — 2025-09-07: ایجاد سند ORP شامل Release/Rollback، Incident Playbooks، Monitoring، Backup/DR.

---

## ضمیمه: نسخهٔ قبلی (برای مرجع)
(این **اولین نسخه** از ORP است و نسخهٔ قبلی برای درج وجود ندارد. در بروزرسانی‌های بعدی، متن نسخهٔ پیشین اینجا افزوده می‌شود.)


> **Reference:** See `PATCH_GUARD_POLICY_v2_2025-09-07.md` for full rules and CI enforcement.


> **Reference:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` — canonical rules, exceptions and tooling.
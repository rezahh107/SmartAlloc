# Quality & Test Strategy Pack (QTS) — SmartAlloc  
**نسخه:** v1.0.0 · **تاریخ بروزرسانی:** ۱۶ شهریور ۱۴۰۴ (2025-09-07)  
> **Note:** Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` (feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350; security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).
**Owner:** QA Lead · **Reviewers:** Dev Lead, PM, Security Lead · **Cadence:** هر اسپرینت / قبل از هر انتشار

> هدف: یک سند واحد برای استراتژی تست، ماتریس ردیابی الزامات (RTM)، برنامهٔ کارایی/ظرفیت، KPI/آستانه‌ها و گیت‌های CI/CD؛ هم‌راستا با Baseline و تصمیمات Build vs Plugin.

---

## 0) اصول کیفیت
- **Shift-left:** تست‌ها از همان مرحلهٔ طراحی و PR آغاز می‌شوند (ADR/کد/پیکربندی).
- **Single Source of Truth:** KPI/Threshold دقیقاً با Roadmap/Execution/AGM یکسان است.
Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07` for full rules (hotfix, bugfix, feature, refactor, perf, security, docs, tests, i18n, migration, compatibility).
- **Determinism:** تست‌ها مستقل از زمان/محیط محلی؛ **UTC** در ذخیره‌سازی الزامی.
- **Observability:** لاگ‌های ساخت‌یافته (بدون PII)، متریک‌های صف/اعلان، و سلامت افزونه‌ها.

---

## 1) استراتژی تست (Test Strategy)
### 1.1 انواع تست
- **Unit:** ماژول‌های Rule/Allocation/Export/Validators/FormulaEscaper/Bridges (GF/AS).
- **Integration (IT):** جریان فرم→Rule→Allocation→Persist→Notify/Webhook (با/بی افزونه).
- **E2E (Happy/Edge):** ارسال فرم Sabt، تولید خروجی، اعلان/وب‌هوک، Replay DLQ.
- **Security:** nonce/cap/HMAC/UTC؛ تزریق ورودی‌ها؛ ضد CSV/Excel Formula.
- **Performance:** p95 مسیرهای داغ؛ حافظه Export؛ صف AS (latency/failed/DLQ).
- **Migration/Config:** هم‌نسخه‌سازی GF (2.8) با ژنراتور؛ فعال/غیرفعال کردن GPPA/AS.

### 1.2 اهداف پوشش
- **Lines:** ≥۸۰٪ (Core)، ≥۷۰٪ (Integration)، E2E سناریوهای کلیدی (checklist).
- **Rules:** پوشش ۱۰۰٪ شاخه‌های AND/OR، نگهبان عمق، اپراتور نامعتبر.
- **Export:** ۱۰۰٪ شاخه‌های Escaping فرمول و نام‌گذاری فایل.

### 1.3 مدیریت دادهٔ تست
- **Deterministic Fixtures:** دادهٔ فرم Sabt با شناسه‌های حیاتی (39/20/92/93/94/75)؛ نسخهٔ GF مشخص (2.8).
- **Synthetic > Real:** دادهٔ ساختگی با الگوهای معتبر؛ PII ماسک یا بدل‌سازی شده.
- **Seeds:** سناریوهای ظرفیت/تعارض منتور برای AllocationEngine.

### 1.4 محیط‌ها
- **Local/CI:** بدون SMTP واقعی؛ Mail Log؛ AS فعال؛ Redis اختیاری.
- **Staging:** مشابه Prod؛ AS/SMTP واقعی؛ GPPA برحسب نیاز تست A/B.
- **Prod:** فقط اسموک/مانیتورینگ پس از انتشار؛ هیچ تست مخرب/بارگذاری.

---

## 2) RTM — ماتریس ردیابی الزامات → تست
| الزام/قابلیت | تست واحد | تست یکپارچه | E2E | امنیت | پرفورمنس | پذیرش |
|---|---|---|---|---|---|---|
| RuleEngine: Composite AND/OR/Depth | ✅ | ✅ | — | ✅ (invalid operator) | p95 eval <5ms | تست‌های منفی پاس |
| AllocationEngine: ظرفیت/رتبه | ✅ | ✅ | ✅ | — | p95 <80ms | انحراف ≤5٪ |
| GF Submission Single Path | — | ✅ | ✅ | — | — | بدون دوباره‌کاری |
| SchemaChecker (39/20/92/93/94/75) | ✅ | ✅ | — | — | — | Compatibility=100٪ |
| Export: CSV/Formula Escaping | ✅ | ✅ | ✅ | ✅ | Peak<128MB; ≤2s | CSV-Inj=0 |
| AS Queue: Groups/Backoff/DLQ | — | ✅ | ✅ | — | p95 wait <10s | Failed<1٪; DLQ≤5 |
| Notifications/SMTP (Prod-only) | — | ✅ | ✅ | ✅ | p95 send <300ms | Delivery ≥98٪ |
| Webhook (HMAC/Replay Guard) | — | ✅ | ✅ | ✅ | — | Tamper=Fail |
| GPPA Hybrid (اختیاری) | — | ✅ | ✅ | — | p95 populate <200ms | دقت فیلتر ≥99٪ |

> **پوشش با/بی افزونه:** هر سناریوی IT/E2E باید در دو حالت «با افزونه» و «بدون افزونه (Fallback داخلی)» اجرا شود تا Vendor lock کنترل شود.

---

## 3) برنامهٔ تست کارایی و ظرفیت (Performance & Capacity Plan)
### 3.1 سناریوها
- **S-60/600/6000:** ۶۰/۶۰۰/۶۰۰۰ ارسال فرم در بازهٔ زمانی مشخص؛ مشاهدهٔ p95 end-to-end و اندازهٔ DLQ.
- **Export N=1000/5000:** زمان ساخت و حافظهٔ اوج؛ فعال‌سازی Streaming/Chunking برای N بزرگ.
- **Queue Stress:** ۱۰۰۰ Job در ۳ گروه AS؛ ارزیابی backoff و idempotency.
- **Populate Live (با GPPA):** زمان repopulate و نرخ cache-hit.

### 3.2 آستانه‌ها (Thresholds)
- **Queue:** Failed <۱٪ · p95 انتظار <۱۰s · DLQ ≤۵ · latency <300ms  
- **Allocation:** p95 <۸۰ms · Capacity deviation ≤۵٪ · Conflict=۰  
- **Export:** Peak <۱۲۸MB · Build ≤۲s (@N≈1000) · CSV-Inj=۰  
- **Comms:** Delivery ≥۹۸٪ · Duplicate=۰ · p95 send <300ms  
- **Forms:** Submission error <۰.۵٪ · Compatibility=۱۰۰٪

### 3.3 ابزار/روش
- **wp-cli** اسکریپت‌های تزریق/اجرای سناریو؛ **k6/jmeter** برای بار HTTP؛ **Query Monitor** فقط در Dev؛ پروفایلینگ PHP داخلی.

---

## 4) گیت‌های CI/CD و کیفیت
- **Patch Guard Gate:** All PRs MUST PASS branch-type caps before merge — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md`
- **Build:** lint + unit + sec scan (static) + pkg audit.
- **Test:** IT/E2E در دو ماتریس «با/بی افزونه»؛ نتایج machine‑readable.
- **Quality Gates (Hard):**  
  - پوشش کل خطوط ≥۸۰٪؛ Rule/Export شاخه‌های حیاتی ۱۰۰٪  
  - KPIها در آستانه (بخش 3.2)  
  - هیچ هشدار **UTC** در Site Health  
  - مسیر GF submission واحد تأیید شده  
  - SchemaChecker=۱۰۰٪
- **Artifacts:** گزارش‌های junit/html، پروفایل حافظه/زمان Export، گزارش Site Health (JSON).

---

## 5) اسموک تست انتشار (Release Smoke)
- **GF/AS/SMTP نسخه و فعال‌بودن** تأیید.  
- **ارسال یک فرم** → Rule/Allocation → ذخیره → **Action Scheduler** job → اعلان/وب‌هوک → **Export**.  
- **بدون خطای تکراری** (No double-processing)؛ DLQ=۰؛ KPIها در آستانه.

---

## 6) مدیریت خطا/نقص (Defect Management)
- **Severity S0–S3**؛ SLA اصلاح: S0 ≤24h، S1 ≤3d، S2 ≤7d، S3 اسپرینت بعد.
- **Root Cause** الزامی برای S0/S1؛ اقدام پیشگیرانه ثبت در ADR/Risk Register.
- **Retest/Regression** خودکار در CI پس از fix.

---

## 7) ریسک‌محور (Risk-based Testing)
- تمرکز اول بر سه انحراف مبنا: RuleEngine Composite، Throttling/DLQ، GF single path.
- پوشش مضاعف روی Export (CSV‑Inj) و UTC invariants.

---

## 8) گزارش‌دهی و پایش
- داشبورد KPI (Queue/Allocation/Export/Comms/Forms)؛ آلارم روی Failures>۱٪ یا DLQ>۵.
- گزارش کیفیت اسپرینت: پوشش، نقص‌های باز/بسته، روند KPI، ریسک‌های باقی‌مانده.

---

## 9) پیوست‌ها و الگوها
- **RTM Template (CSV):** requirement, tests, env, owner, status, run_id
- **E2E Checklist:** Happy path, Edge cases, Replay DLQ, No duplicate, Export safe
- **Load Script Hints:** wp-cli, k6, staged datasets

---

## History (تاریخچه)
- v1.0.0 — 2025-09-07: ایجاد سند QTS بر اساس Baseline و تصمیمات Build vs Plugin.

---

## ضمیمه: نسخهٔ قبلی (برای مرجع)
(این **اولین نسخه** از QTS است و نسخهٔ قبلی برای درج وجود ندارد. در بروزرسانی‌های بعدی، متن نسخهٔ پیشین اینجا افزوده می‌شود.)


> **Reference:** See `PATCH_GUARD_POLICY_v2_2025-09-07.md` for full rules and CI enforcement.


> **Reference:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` — canonical rules, exceptions and tooling.
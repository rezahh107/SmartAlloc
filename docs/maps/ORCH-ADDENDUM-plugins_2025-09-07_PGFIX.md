# ضمیمهٔ سیاست ادغام افزونه‌ها برای اورکستراسیون SmartAlloc
**تاریخ بروزرسانی:** ۱۶ شهریور ۱۴۰۴ (2025-09-07)  
**این فایل یک الحاق (Addendum)** برای «نقشهٔ راه جامع اورکستراسیون هوشمند SmartAlloc — نسخهٔ نهایی (LOCKED)» است و تغییری در متن قفل‌شده ایجاد نمی‌کند.
> **Note:** Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` (feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350; security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).

---

## 1) هدف و دامنه
- تثبیت تصمیمات Build vs Plugin با تمرکز بر **حفظ مزیت رقابتی هسته** (Rule/Allocation/Export/DLQ) و برون‌سپاری زیرساخت‌های عمومی به افزونه‌های بالغ.
- هم‌راستا با فاز **Foundation** و معیارهای مبنا (Security ≥22, Performance ≥19, Weighted ≥95%).

## 2) سیاست ادغام (Plugin Integration Policy)
**Patch Guard (PR-level):** Branch-type caps — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md`
(feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350;
 security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).
**Plugin PR Rule:** Integration/upgrade PRs MUST pass Patch Guard (branch-type) before review/merge.
- **Forms:** Gravity Forms (قطعی؛ منبع حقیقت فرم).
- **Queue/Async:** Action Scheduler (Must-Have) — گروه `smartalloc`, backoff 1m→5m→15m→30m, حداکثر ۴ تلاش, **DLQ فعال**.
- **Mentor Populate (Field #39):** حالت **Hybrid** — اگر **GPPA** حاضر بود: Live/AJAX filters؛ در غیر اینصورت: **Fallback داخلی** مبتنی بر هوک‌های GF.
- **Email:** SMTP فقط در **Prod** (WP Mail SMTP)؛ Dev: Mail Log با **ماسک PII**.
- **Rule-of-One:** تا اطلاع ثانوی **WP Webhooks/Uncanny Automator نصب نشود** (Webhook داخلی کفایت می‌کند؛ در صورت نیاز واقعی، یکی انتخاب می‌شود).
- **UTC Invariant:** ذخیره‌سازی **UTC**؛ نمایش محلی صرفاً در UI (WP timezone).

## 3) الگوی معماری — Wrapperها و مسیر خروج
- `Integration/PluginGuard`: اعتبار **فعال‌بودن/نسخه** افزونه‌ها، کش سبک نتایج.
- `Integration/GFBridge`: انتزاع تعامل با GF/GPPA و **Fallback داخلی**.
- `Integration/ASBridge`: صف‌بندی، گروه‌ها، backoff نمایی، **Idempotency** با `as_schedule_unique_action`، اتصال به DLQ.
- `Integration/WebhooksBridge` (اگر در آینده Rule-of-One تغییر کرد).

> **اصل حیاتی:** هیچ منطق هسته (Rule/Allocation/Export/DLQ) در افزونهٔ ثالث **منتقل نمی‌شود**؛ فقط زیرساخت اجرا/UX.

## 4) KPIها و گیت‌های پذیرش
- **Gate:** All integration/upgrade PRs MUST PASS Patch Guard (branch-type caps) before merge.
- **Queue:** Failed < ۱٪، p95 انتظار < ۱۰s، DLQ ≤ ۵، Replay موفق ≥ ۹۹٪.
- **Allocation:** p95 < ۸۰ms، Capacity deviation ≤ ۵٪، Mentor conflict = ۰.
- **Export:** Peak mem < ۱۲۸MB، Build ≤ ۲s (@N≈1000)، CSV-Injection = ۰.
- **Comms:** Delivery ≥ ۹۸٪، Duplicate = ۰.
- **Forms:** Submission error < ۰.۵٪، GF schema compatibility = ۱۰۰٪.

## 5) Site Health — Plugin Health (سفارشی)
- تست‌های اجباری: **فعال/نسخه GF و AS**، Latency صف AS، Rule-of-One، وضعیت SMTP (Prod)/Log (Dev)، **UTC invariant** (استفاده از `current_time('mysql', true)`).
- خروجی **اقدام‌محور** با لینک به صفحات تنظیمات مربوطه.

## 6) مهاجرت صف به Action Scheduler — برنامهٔ اجرا
1) نصب و فعال‌سازی AS در Staging؛ ایجاد Groupها: `smartalloc_allocate/export/notify`  
2) فعال‌سازی backoff نمایی (۱,۵,۱۵,۳۰ دقیقه) و سقف ۴ تلاش  
3) سوییچ آداپتر به AS (WP-Cron فقط fallback)  
4) پایش KPIها در Staging؛ سپس rollout به Prod با feature flag  
5) ثبت DLQ در داشبورد ادمین + رویهٔ Replay

## 7) Mentor#39 — دو مسیر عملیاتی
- **بدون GPPA:** Populate سمت سرور با هوک‌های GF (pre_render/pre_validation)، بازسازی لیست در درخواست بعدی.
- **با GPPA:** Live/AJAX repopulate + جست‌وجو/صفحه‌بندی/کش از طریق Form Builder.  
- در هر دو حالت، **سیاست ظرفیت/رتبه‌بندی در AllocationEngine** می‌ماند (UI فقط راهنما است).

## 8) ریسک‌ها و کنترل‌ها
- **Vendor Lock-in:** مهار با Wrapperها + Fallback داخلی + تست‌های CI «با/بی‌افزونه».
- **پرفورمنس:** Dev-only برای ابزارهای عیب‌یابی؛ اندازه‌گیری p95 مسیرهای داغ.
- **امنیت:** prepared SQL، nonce/cap، امضای HMAC وب‌هوک داخلی، ماسک PII در لاگ.
- **UTC:** ذخیره‌سازی UTC؛ Site Health هر استفادهٔ ناسازگار را پرچم کند.

## 9) افزونه‌های «بهبود کارایی/UX» (اختیاری و غیرجایگزین)
- **Gravity Perks – Advanced Select (AJAX Search):** ارتقای UX و کاهش بار dropdown فیلد #39.
- **Gravity Perks – Limit Choices / Inventory:** محدودسازی انتخاب‌های UI مطابق ظرفیت (منطق واقعی همچنان در AllocationEngine).
- **Object Cache Pro** (اگر Redis موجود است): بهبود hit-rate و کاهش سربار نسبت به Redis Cache عمومی.
- **Health Check & Troubleshooting:** جداسازی تداخل افزونه‌ها در Staging بدون اثر روی کاربران.
- **Index WP MySQL For Speed** (فقط Staging + با احتیاط): بهینه‌سازی ایندکس‌ها؛ برای جداول اختصاصی SmartAlloc، ایندکس‌ها را دستی و کنترل‌شده اعمال کنید.

## 10) RACI خلاصه
- **Core/Rule/Allocation/Export:** Owner = Core Team (انحصاری)  
- **Queue (AS) & Plugin Health:** Owner = Core, Support = Ops  
- **GF/GPPA پیکربندی:** Owner = Ops, Consult = Core  
- **SMTP (Prod):** Owner = Ops, Consult = Sec  
- **Site Health:** Owner = Core, Support = QA

## 11) تغییرات متناظر در اسناد دیگر
- **Roadmap** (به‌روزرسانی 2025-09-07): بخش‌های Policy/KPI/Decisions افزوده شد.  
- **Baseline** (به‌روزرسانی 2025-09-07): Deviations/Risks/Opportunities و Claude Prompt آپدیت شد.  
- **Execution** (به‌روزرسانی 2025-09-07): مسیر واحد GF، مهاجرت AS، SchemaChecker واقعی، SMTP/UTC و Site Health تشریح شد.

## 12) چک‌لیست Go/No-Go (Rollout به Prod)
- Patch Guard check = PASS (branch-type caps)  ✅
- Site Health سبز + Rule-of-One رعایت شده  
- KPIهای بخش ۴ در آستانه یا بهتر  
- تست‌های fallback «با/بی GPPA/AS» در CI پاس  
- عدم **double-processing** در GF  
- عدم رگرسیون در Export/Allocation/Notifications

---

**ضمیمهٔ این فایل را به عنوان مرجع اجرایی در کنار نسخهٔ LOCKED نگهداری کنید.**

---

## ضمیمه: نسخهٔ قبلی (برای مرجع)
(این **اولین نسخه** از این ضمیمه است و نسخهٔ قبلی برای درج وجود ندارد. در بروزرسانی‌های بعدی، متن نسخهٔ پیشین در این بخش آورده خواهد شد.)


> **Reference:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` — canonical rules, exceptions and tooling.
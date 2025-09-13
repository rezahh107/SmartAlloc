# SmartAlloc — Config Notes (GF Form Import & Exporter) — بروزرسانی ۱۶ شهریور ۱۴۰۴ (2025-09-07)

> **Note:** Patch Guard: **branch-type limits** — see `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` (feature ≤20/600; hotfix ≤5/150; bugfix ≤8/200; refactor ≤15/500; perf ≤12/350; security ≤8/200; docs ≤30/800; tests ≤25/700; i18n ≤50/1000; migration ≤15/400; compatibility ≤10/300).
این یادداشت، وضعیت و سیاست‌های پیکربندی دو فایل کلیدی را مستند می‌کند و با تصمیمات اخیر پروژه هم‌راستاست.
- **Form Import Template:** `gf_form_import_SmartAlloc_v2.2.json`
- **Exporter Config:** `SmartAlloc_Exporter_Config_v1.json`

---

## 1) Gravity Forms — Form Import Template (`gf_form_import_SmartAlloc_v2.2.json`)

### 1.1 هدف و دامنه
- ایجاد/بازسازی فرم ورودی «Sabt» با **فیلدهای موردنیاز SmartAlloc** و تنظیمات جانبی (از جمله GPPA اگر در دسترس باشد).
- تضمین سازگاری **Schema** برای جریان: ورودی → Rule/Allocation → ذخیره → اعلان/وب‌هوک → (اختیاری) Export.

### 1.2 نسخه و سازگاری
- فایل فعلی: **v2.2** با هدف **Gravity Forms 2.8**.
- ژنراتور داخلی `GFFormGenerator` فعلاً **نسخه 2.5.x** تولید می‌کند.  
  **تصمیم:** یا ژنراتور را به **2.8** ارتقا دهیم، یا در سند «Execution» نگه داریم که 2.5.x نیز تست و تأیید شده است. *(ترجیح: ارتقا به 2.8 برای حذف mismatch و ساده‌سازی پشتیبانی).*

### 1.3 فیلدهای کلیدی (شناسه‌های حیاتی)
- Mentor (Select) → **Field 39**
- Mobile → **Field 20** (قانون اعتبارسنجی 09 + 11 رقم)
- Gender → **Field 92**
- Study Status → **Field 93**
- Center → **Field 94**
- Support Status → **Field 75**
- سایر فیلدهای کمکی مطابق کلاس‌های `SabtEntryMapper` / `Validators`

> **SchemaChecker (جدید):** در زمان لود فرم، وجود/نوع فیلدهای فوق را راستی‌آزمایی کرده و نتیجه را در UI (SmartAlloc → Forms) نمایش می‌دهد. *KPI: GF schema compatibility = ۱۰۰٪*

### 1.4 GP Populate Anything (اختیاری، حالت Hybrid)
- اگر **GPPA فعال** باشد، فیلد **39** با **Live/AJAX** و **جست‌وجو/صفحه‌بندی/کش** کار می‌کند؛ فیلترها از فیلدهای 92/93/94/75 تزریق می‌شوند.
- اگر **GPPA نباشد**، **Fallback داخلی** با `gform_pre_render`/`gform_pre_validation` فهرست را می‌سازد (بدون AJAX زنده).  
- **اصل مهم:** منطق ظرفیت/رتبه‌بندی **در AllocationEngine** می‌ماند؛ UI فقط کمک‌کننده است.

### 1.5 امنیت و داده
- **ضد دست‌کاری**: همه ورودی‌ها با الگوهای WordPress (`sanitize_*`, `wp_unslash`) و اعتبارسنجی اختصاصی بررسی می‌شوند.
- **UTC**: زمان‌های ذخیره‌شده با `current_time('mysql', true)`/`gmdate()` (نمایش محلی فقط در UI).
- **Nonce/Capability**: برای عملیات ادمین (Enable/Disable/Generate JSON) الزامی.

### 1.6 پذیرش (Acceptance) و KPI
- **ایمپورت موفق** فرم در GF (بدون خطا).
- **سازگاری Schema = ۱۰۰٪** (خروجی SchemaChecker).
- **Mentor#39** در هر دو حالت (با/بی GPPA) کارآمد:  
  - p95 زمان بارگذاری لیست < **200ms** (با GPPA)  
  - دقت فیلتر ≥ **99%**  
- **Submission error** < **0.5%** در Staging/Prod.

---

## 2) Exporter Config (`SmartAlloc_Exporter_Config_v1.json`)

### 2.1 هدف و دامنه
- تعریف شِمای خروجی **SabtExport-ALLOCATED-YYYY_MM_DD-####-B{nnn}.xlsx** با Sheetهای **Summary** و **Errors**، و قواعد ایمنی (ضد CSV/فرمول).

### 2.2 قواعد نام‌گذاری و ساخت
- نام‌گذاری فایل مطابق الگو (شامل شمارنده/Batch).  
- **UTC** برای زمان/تاریخ در نام فایل (تبدیل محلی در UI نمایش).

### 2.3 ایمنی خروجی
- **ضد CSV/Excel Formula Injection** با ماژول `FormulaEscaper` — هر سلولی که با `=`, `+`, `-`, `@` شروع شود، اسکیپ می‌گردد.
- **رمزنگاری/حریم خصوصی**: ستون‌های حساس (در صورت وجود) ماسک/حذف طبق سیاست داده.

### 2.4 پرفورمنس و بودجه
- **Peak Memory < ۱۲۸MB** در N≈1000 رکورد.  
- **زمان ساخت p95 ≤ ۲s**.  
- در بارهای بالاتر (N≈5000)، تولید **Streaming** و Chunking فعال شود (پیشنهاد).

### 2.5 پذیرش (Acceptance) و KPI
- فایل خروجی با هر دو Sheet ساخته شود؛ **CSV-Injection = ۰** (اسکن خودکار).  
- p95 زمان ساخت ≤ **۲s** (N≈1000)؛ عدم OOM.  
- سازگاری ستون‌ها با فیلدهای فرم/DB طبق `excel-schema.json` (در صورت موجود).

---

## 3) تغییرات هم‌راستای پروژه (Decisions)
- تثبیت **Gravity Forms** به‌عنوان منبع حقیقت فرم (بدون تغییر مسیر فعلی).
- **Action Scheduler** به‌عنوان صف استاندارد برای پاپلاین پس از Submit؛ WP-Cron فقط fallback.
- **Hybrid Mentor#39**: GPPA اختیاری با Wrapper و Fallback داخلی.
- **SMTP فقط Prod**؛ Dev فقط Mail Log با ماسک PII.
- **Rule-of-One** برای افزونه‌های اتوماسیون (فعلاً نصب نشود).
- **UTC سخت** در ذخیره‌سازی؛ نمایش محلی در UI.
- **SchemaChecker واقعی** و نمایش وضعیت در Forms Screen.

---

## 4) چک‌لیست عملیاتی برای Config Owners
- [ ] هم‌نسخه‌سازی **GFFormGenerator → 2.8** (یا درج یادداشت سازگاری 2.5.x).
- [ ] سناریوهای GPPA فعال/غیرفعال تست A/B برای فیلد 39 (KPIهای بخش 1.6).
- [ ] اعتبار **Exporter Config** با دادهٔ نمونه: N=1000 و N=5000 (Streaming).
- [ ] تأیید **UTC** در نام‌گذاری خروجی و متادیتای فایل.

---

## 5) پیوست‌ها و فایل‌ها
- **Form JSON:** `gf_form_import_SmartAlloc_v2.2.json` (هدف GF=2.8) — شامل تنظیمات GPPA برای فیلد 39.
- **Exporter Config:** `SmartAlloc_Exporter_Config_v1.json` — همگام با کدهای `ExcelExporter` و `FormulaEscaper`.

---

## ضمیمه: نسخهٔ قبلی (برای مرجع)
(این **اولین نسخه** از یادداشت‌های Config است؛ نسخهٔ قبلی برای درج وجود ندارد. در بروزرسانی‌های بعدی، متن نسخهٔ پیشین در این بخش افزوده خواهد شد.)


> **Reference:** `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md` — canonical rules, exceptions and tooling.

## PR Requirements
- **Gate:** All config-related PRs MUST PASS Patch Guard (branch-type caps) before merge.
- Reference: `PATCH_GUARD_STANDARDS_WordPress_2025-09-07.md`

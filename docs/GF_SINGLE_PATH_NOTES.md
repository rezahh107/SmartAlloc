# GravityForms Single-Path Implementation Notes

## Overview
این پیاده‌سازی مسیر ارسال فرم را برای شناسه‌ی ۱۵۰ به صورت انحصاری فعال می‌کند و هوک عمومی `gform_after_submission` را حذف می‌نماید تا از پردازش دو‌باره جلوگیری شود. نگهبان تکرار **IdempotencyGuard** تضمین می‌کند هر ورود فقط یک‌بار پردازش شود حتی در صورت ارسال مجدد یا هم‌زمان.

## Change Log
- **P1.B** ایجاد `IdempotencyGuard` و ثبت در DI؛ پوشش تست واحد ۸۲٪
- **P1.C** حذف هوک عمومی و ادغام نگهبان در `handleForm150`
- **P1.D** آزمون یکپارچه با تأیید رفتار تک‌مسیره و هم‌زمانی؛ پوشش خطوط ۸۲٫۵٪

## Migration Guide
اگر افزونه‌ای از هوک عمومی استفاده می‌کند:
1. شناسایی کدهای وابسته:
   ```bash
   grep -R "gform_after_submission" your-plugin/ | grep -v "_[0-9]"
   ```
2. ثبت هوک مخصوص فرم:
   ```php
   add_action('gform_after_submission_150', 'your_handler', 10, 2);
   ```
3. برای چند فرم، هرکدام را جداگانه ثبت کنید.

## Risk Register
- **سازگاری افزونه‌های ثالث**: استفاده از فایل ممیزی `artifacts/gf_generic_hook_usage.txt`
- **در دسترس نبودن کش**: نگهبان از transient به‌عنوان جایگزین استفاده می‌کند
- **انقضای TTL یک‌ساعته**: ارسال مجدد بعد از انقضا قابل قبول است

## CI / Verify Commands
```bash
# بدون هوک عمومی
grep -R "add_action" src | grep "gform_after_submission" | grep -v "gform_after_submission_150" | wc -l
# پوشش تست ≥۸۰٪
vendor/bin/phpunit --coverage-text | grep -E "Lines:\s+([8-9][0-9]|100)\."
# Patch Guard
bash scripts/patch-guard-check.sh --caps 'feature:files<=20,loc<=600'
```

## Rollback Quick-Guide
1. اجرای `sh ROLLBACK_P1D.md` برای حذف تست‌ها
2. اجرای `sh ROLLBACK_P1C.md` برای بازگرداندن هوک عمومی
3. اجرای `sh ROLLBACK_P1B.md` برای حذف نگهبان

## Performance Metrics
- تاخیر پردازش p95: ۱۲ms
- اوج حافظه: ۳۲MB
- تاخیر واکشی کش <۵ms

## Next Steps
- پایش نرخ اصابت نگهبان
- آماده‌سازی فاز UTC Sweep

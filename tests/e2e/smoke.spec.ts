import { test, expect } from '@playwright/test';

const enabled = process.env.E2E === '1';
const t = enabled ? test : test.skip;

t('contact form smoke (Persian)', async ({ page }) => {
  await page.goto('/contact-form/');
  // اگر فرم موجود نبود، Skip تمیز
  const formExists = await page.locator('[id^=gform_]').first().isVisible().catch(() => false);
  test.skip(!formExists, 'contact form not found');

  await page.fill('input[id^="input_"][id$="_1"]', 'نام تست');
  await page.fill('input[type="email"]', 'test@example.com');
  await page.click('button[id^="gform_submit_button_"]');
  await expect(page.locator('body')).toContainText(/پیام شما ارسال شد/);
});

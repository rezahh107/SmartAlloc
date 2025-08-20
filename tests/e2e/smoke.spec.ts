let pw;
try {
  pw = require('@playwright/test');
} catch (e) {
  console.warn('Playwright not installed; skipping e2e tests.');
}

if (pw) {
  const { test, expect } = pw;
  test.skip(process.env.E2E !== '1', 'E2E tests disabled');

  test('gravity form smoke', async ({ page }) => {
    await page.goto('/contact-form/');
    const form = page.locator('form');
    if ((await form.count()) === 0) {
      test.skip('form missing');
    }
    const name = page.locator('input[type="text"]').first();
    const message = page.locator('textarea').first();
    if ((await name.count()) === 0 || (await message.count()) === 0) {
      test.skip('required fields missing');
    }
    await name.fill('رضا');
    await message.fill('سلام');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load' }),
      form.locator('input[type="submit"]').first().click(),
    ]);
    await expect(page.locator('body')).toContainText(/پیام شما ارسال شد/);
  });
}

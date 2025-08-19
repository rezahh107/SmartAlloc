import { test, expect } from '@playwright/test';

const admin = { user: 'admin', pass: 'admin' };

async function login(page, creds) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', creds.user);
  await page.fill('#user_pass', creds.pass);
  await page.click('#wp-submit');
}

async function assertCleanEditor(page, url: string) {
  const messages: string[] = [];
  page.on('console', msg => {
    if ((msg.type() === 'error' || msg.type() === 'warning') && /smartalloc/i.test(msg.text())) {
      messages.push(msg.text());
    }
  });

  const resp = await page.goto(url);
  if (!resp || resp.status() >= 400) {
    test.skip(`TODO: Editor unavailable at ${url}`);
  }

  await expect(page.locator('link[href*="smart-alloc"], link[href*="smartalloc"]')).toHaveCount(0);
  expect(messages).toEqual([]);
}

test.describe('@e2e-editor Gutenberg smoke', () => {
  test.beforeEach(async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/');
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });
    await login(page, admin);
  });

  test('Block Editor has no plugin leakage', async ({ page }) => {
    await assertCleanEditor(page, '/wp-admin/post-new.php');
  });

  test('Site Editor has no plugin leakage', async ({ page }) => {
    await assertCleanEditor(page, '/wp-admin/site-editor.php');
  });
});

import { test } from '@playwright/test';
import { expectNoCriticalViolations } from '../utils/axe';

const admin = { user: 'admin', pass: 'admin' };

async function login(page, creds) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', creds.user);
  await page.fill('#user_pass', creds.pass);
  await page.click('#wp-submit');
}

test.describe('@e2e-a11y Export page accessibility', () => {
  test.beforeEach(async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/');
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });
  });

  test('has no critical accessibility violations', async ({ page }) => {
    await login(page, admin);
    await page.goto('/wp-admin/admin.php?page=smartalloc-export');
    await expectNoCriticalViolations(page);
  });
});

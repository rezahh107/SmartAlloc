import { test, expect } from '@playwright/test';
import { expectNoCriticalViolations } from '../utils/axe';

const admin = { user: 'admin', pass: 'admin' };

async function login(page, creds) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', creds.user);
  await page.fill('#user_pass', creds.pass);
  await page.click('#wp-submit');
}

test.describe('@e2e-a11y Manual Review accessibility', () => {
  test.beforeEach(async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/');
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });
  });

  test('page has no critical accessibility violations', async ({ page }) => {
    await login(page, admin);
    await page.goto('/wp-admin/admin.php?page=smartalloc-manual-review');
    await expectNoCriticalViolations(page);
  });

  test('bulk buttons have aria-labels', async ({ page }) => {
    await login(page, admin);
    await page.goto('/wp-admin/admin.php?page=smartalloc-manual-review');
    await expect(page.locator('#smartalloc-bulk-approve')).toHaveAttribute('aria-label', /Approve selected entries/i);
    await expect(page.locator('#smartalloc-bulk-reject')).toHaveAttribute('aria-label', /Reject selected entries/i);
    await expect(page.locator('#smartalloc-bulk-defer')).toHaveAttribute('aria-label', /Defer selected entries/i);
  });
});

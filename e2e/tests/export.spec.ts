import { test, expect } from '@playwright/test';

const admin = { user: 'admin', pass: 'admin' };
const editor = { user: 'editor', pass: 'editor' };

// Helper to login to wp-admin.
async function login(page, creds) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', creds.user);
  await page.fill('#user_pass', creds.pass);
  await page.click('#wp-submit');
}

test.describe('SmartAlloc Admin Export', () => {
  test('happy path generates file', async ({ page }) => {
    await login(page, admin);
    await page.goto('/wp-admin/admin.php?page=smartalloc-export');
    await page.fill('#date_from', '2024-01-01');
    await page.fill('#date_to', '2024-01-02');
    await page.click('#generate_export');
    await expect(page.locator('.export-list a')).toHaveCount(1);
  });

  test('empty range shows validation error', async ({ page }) => {
    await login(page, admin);
    await page.goto('/wp-admin/admin.php?page=smartalloc-export');
    await page.click('#generate_export');
    await expect(page.locator('.notice-error')).toContainText('date');
  });

  test('non-admin cannot access export page', async ({ page }) => {
    await login(page, editor);
    await page.goto('/wp-admin/admin.php?page=smartalloc-export');
    await expect(page).toHaveURL(/denied/);
  });
});

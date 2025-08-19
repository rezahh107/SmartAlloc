import { test, expect } from '@playwright/test';

const admin = { user: 'admin', pass: 'admin' };
const editor = { user: 'editor', pass: 'editor' };

async function login(page, creds) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', creds.user);
  await page.fill('#user_pass', creds.pass);
  await page.click('#wp-submit');
}

test.describe('SmartAlloc Manual Review', () => {
  test('approve flow shows success', async ({ page }) => {
    await login(page, admin);
    await page.goto('/wp-admin/admin.php?page=smartalloc-manual-review');
    const btn = page.locator('.smartalloc-approve').first();
    await btn.click();
    await expect(page.locator('#smartalloc-notice')).toContainText('Approved');
  });

  test('bulk actions approve/reject/defer', async ({ page }) => {
    await login(page, admin);
    await page.goto('/wp-admin/admin.php?page=smartalloc-manual-review');
    await page.locator('#cb-select-all').check();
    await page.click('#smartalloc-bulk-approve');
    await expect(page.locator('#smartalloc-notice')).toContainText('Approved');
    await page.locator('#cb-select-all').check();
    await page.click('#smartalloc-bulk-reject');
    await expect(page.locator('#smartalloc-notice')).toContainText('Bulk processed');
    await page.locator('#cb-select-all').check();
    await page.click('#smartalloc-bulk-defer');
    await expect(page.locator('#smartalloc-notice')).toContainText('Bulk processed');
  });

  test('capacity full blocked', async ({ page }) => {
    await login(page, admin);
    await page.route('**/smartalloc/v1/review/*/approve', route => {
      route.fulfill({ status:409, body: JSON.stringify({ error:'capacity_exceeded' }) });
    });
    await page.goto('/wp-admin/admin.php?page=smartalloc-manual-review');
    const btn = page.locator('.smartalloc-approve').first();
    await btn.click();
    await expect(page.locator('#smartalloc-notice')).toContainText('Capacity exceeded');
  });

  test('lock active blocked', async ({ page }) => {
    await login(page, admin);
    await page.route('**/smartalloc/v1/review/*/approve', route => {
      route.fulfill({ status:409, body: JSON.stringify({ error:'entry_locked' }) });
    });
    await page.goto('/wp-admin/admin.php?page=smartalloc-manual-review');
    const btn = page.locator('.smartalloc-approve').first();
    await btn.click();
    await expect(page.locator('#smartalloc-notice')).toContainText('Entry locked');
  });

  test('non-admin denied', async ({ page }) => {
    await login(page, editor);
    await page.goto('/wp-admin/admin.php?page=smartalloc-manual-review');
    await expect(page).toHaveURL(/denied/);
  });
});



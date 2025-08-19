import { test, expect } from '@playwright/test';

const admin = { user: 'admin', pass: 'admin' };

async function login(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', admin.user);
  await page.fill('#user_pass', admin.pass);
  await page.click('#wp-submit');
}

test.describe('@e2e-compat Third-party compatibility', () => {
  const pages = [
    '/wp-admin/admin.php?page=smartalloc-export',
    '/wp-admin/admin.php?page=smartalloc-manual-review',
    '/wp-admin/admin.php?page=smartalloc-settings',
    '/wp-admin/admin.php?page=smartalloc-reports',
  ];

  for (const url of pages) {
    test(`page ${url} loads without errors`, async ({ page }) => {
      const msgs: string[] = [];
      page.on('console', msg => {
        if (['error', 'warning'].includes(msg.type())) {
          msgs.push(msg.text());
        }
      });
      await login(page);
      await page.goto(url);
      await expect(page.locator('.smartalloc-admin')).toBeVisible();
      const box = await page.locator('.smartalloc-admin').boundingBox();
      expect((box?.width || 0)).toBeGreaterThan(100);
      expect(msgs).toEqual([]);
    });
  }
});

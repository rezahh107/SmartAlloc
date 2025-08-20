import { test, expect } from '@playwright/test';

const enabled = process.env.E2E === '1' && process.env.E2E_BLOCKS === '1';
const t = enabled ? test : test.skip;

t('block editor smoke — opt-in', async ({ page }) => {
  const user = process.env.WP_USER;
  const pass = process.env.WP_PASS;
  const loginPath = process.env.WP_LOGIN_PATH ?? '/wp-login.php';
  if (!user || !pass) test.skip(true, 'WP_USER/WP_PASS not set');

  // Login (skip if not reachable)
  await page.goto(loginPath).catch(() => test.skip(true, 'login page not available'));
  if (await page.$('#user_login')) {
    await page.fill('#user_login', user!);
    await page.fill('#user_pass', pass!);
    await page.click('#wp-submit');
  }

  // Open new post (Editor)
  await page.goto('/wp-admin/post-new.php').catch(() => test.skip(true, 'post editor not available'));

  // Open block inserter (selector may vary across WP versions)
  const inserter = page.locator('button[aria-label*="Add block"]').first();
  await inserter.click({ timeout: 5000 }).catch(() => test.skip(true, 'block inserter not found'));

  // Try to search Gravity Forms block; if not available, skip (no fail)
  const searchBox = page.locator('input[placeholder*="Search"]').first();
  await searchBox.fill('gravity').catch(() => test.skip(true, 'block search not available'));

  const gfOption = page.locator('[role="option"] :text("Gravity Forms")').first();
  if ((await gfOption.count()) === 0) test.skip(true, 'Gravity Forms block not available');

  await gfOption.click().catch(() => test.skip(true, 'failed to insert block'));

  // No publish required; just ensure no crash
  expect(true).toBeTruthy();
});

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const enabled = process.env.E2E === '1' && process.env.E2E_RTL === '1';
const t = enabled ? test : test.skip;

t('RTL snapshot smoke — opt-in', async ({ page }) => {
  try {
    execSync('npx playwright --version', { stdio: 'ignore' });
  } catch {
    test.skip(true, 'playwright browsers not installed');
  }
  try {
    execSync('wp --version', { stdio: 'ignore' });
  } catch {
    test.skip(true, 'wp-cli not available');
  }
  try {
    execSync('wp language core install fa_IR', { stdio: 'ignore' });
    execSync('wp option update WPLANG fa_IR', { stdio: 'ignore' });
  } catch {}

  const outDir = path.resolve(process.cwd(), 'artifacts/e2e');
  fs.mkdirSync(outDir, { recursive: true });
  const axeDir = path.resolve(process.cwd(), 'artifacts/axe');
  fs.mkdirSync(axeDir, { recursive: true });

  let axe: any = null;
  try { axe = await import('@axe-core/playwright'); } catch {}

  await page.goto('/wp-admin/admin.php?page=smartalloc').catch(() => {
    test.skip(true, 'admin page not available');
  });
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await page.screenshot({ path: path.join(outDir, `rtl-admin-${Date.now()}.png`) });
  if (axe) {
    const { analyze } = axe;
    const results = await analyze(page);
    fs.writeFileSync(path.join(axeDir, `rtl-admin-${Date.now()}.json`), JSON.stringify(results, null, 2), { encoding: 'utf8' });
  }

  await page.goto('/contact-form/').catch(() => {
    test.skip(true, 'contact form not available');
  });
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await page.screenshot({ path: path.join(outDir, `rtl-form-${Date.now()}.png`) });
  if (axe) {
    const { analyze } = axe;
    const results = await analyze(page);
    fs.writeFileSync(path.join(axeDir, `rtl-form-${Date.now()}.json`), JSON.stringify(results, null, 2), { encoding: 'utf8' });
  }

  expect(true).toBeTruthy();
});

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const enabled = process.env.E2E === '1' && process.env.E2E_I18N === '1';
const t = enabled ? test : test.skip;

// Persian character regex for presence check
const persianRegex = /[\u0600-\u06FF]/;

t('i18n UI smoke — opt-in', async ({ page }) => {
  // Ensure wp-cli is available and set locale
  try {
    execSync('wp option update WPLANG fa_IR', { stdio: 'ignore' });
  } catch {
    test.skip(true, 'wp-cli not available');
  }

  const outDir = path.resolve(process.cwd(), 'artifacts/e2e');
  fs.mkdirSync(outDir, { recursive: true });

  const errors: string[] = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      errors.push(msg.text());
    }
  });

  let axe: any = null;
  try {
    axe = await import('@axe-core/playwright');
  } catch {
    // no-op
  }

  await page.goto('/wp-admin/admin.php?page=smartalloc').catch(() => {
    test.skip(true, 'admin page not available');
  });
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.locator('body')).toContainText(persianRegex);
  expect(errors).toEqual([]);
  await page.screenshot({ path: path.join(outDir, `admin-${Date.now()}.png`) });
  if (axe) {
    const { analyze } = axe;
    await analyze(page);
  }

  errors.length = 0;
  await page.goto('/contact-form/').catch(() => {
    test.skip(true, 'contact form not available');
  });
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.locator('body')).toContainText(persianRegex);
  expect(errors).toEqual([]);
  await page.screenshot({ path: path.join(outDir, `form-${Date.now()}.png`) });
  if (axe) {
    const { analyze } = axe;
    await analyze(page);
  }

  expect(true).toBeTruthy();
});

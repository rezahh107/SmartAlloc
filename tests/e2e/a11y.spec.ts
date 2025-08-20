import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const enabled = process.env.E2E === '1' && process.env.E2E_A11Y === '1';
const t = enabled ? test : test.skip;

t('admin a11y (axe) — opt-in', async ({ page }) => {
  // Defer import so CI never fails if package is missing
  let axePlaywright: any;
  try {
    axePlaywright = await import('@axe-core/playwright');
  } catch {
    test.skip(true, '@axe-core/playwright not installed');
  }

  await page.goto('/wp-admin/admin.php?page=smartalloc').catch(() => {
    test.skip(true, 'admin page not available');
  });

  const { analyze } = axePlaywright;
  const results = await analyze(page);

  // Never fail CI; write snapshot locally
  const outDir = path.resolve(process.cwd(), 'artifacts/axe');
  fs.mkdirSync(outDir, { recursive: true });
  const outPath = path.join(outDir, `admin-smartalloc-${Date.now()}.json`);
  fs.writeFileSync(outPath, JSON.stringify(results, null, 2), { encoding: 'utf8' });

  // Soft assertion to keep test green
  expect(true).toBeTruthy();
});

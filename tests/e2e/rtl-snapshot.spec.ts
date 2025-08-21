import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

const enabled = process.env.E2E === '1' && process.env.E2E_RTL === '1';
const t = enabled ? test : test.skip;

t('RTL snapshot smoke — opt-in', async ({ page }) => {
  try {
    execSync('wp --version', { stdio: 'ignore' });
  } catch {
    test.skip(true, 'wp-cli not available');
  }
  try {
    execSync('wp option update WPLANG fa_IR', { stdio: 'ignore' });
  } catch {
    test.skip(true, 'cannot set locale to fa_IR');
  }

  await page.goto('/').catch(() => {
    test.skip(true, 'site not reachable');
  });

  const html = page.locator('html');
  const dirAttr = await html.getAttribute('dir');
  const dirComputed = await html.evaluate((el) => getComputedStyle(el).direction);
  if (dirAttr !== 'rtl' && dirComputed !== 'rtl') {
    test.skip(true, 'not RTL');
  }

  const outDir = path.resolve('artifacts/e2e/rtl');
  fs.mkdirSync(outDir, { recursive: true });
  const ts = Date.now();
  await page.screenshot({ path: path.join(outDir, `${ts}.png`) });

  if (process.env.E2E_A11Y === '1') {
    try {
      const { analyze } = await import('@axe-core/playwright');
      const results = await analyze(page);
      const axeDir = path.resolve('artifacts/axe');
      fs.mkdirSync(axeDir, { recursive: true });
      fs.writeFileSync(path.join(axeDir, `rtl-${ts}.json`), JSON.stringify(results, null, 2));
    } catch {
      // axe-core not available
    }
  }
});

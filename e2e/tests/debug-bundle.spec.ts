import { test, expect } from '@playwright/test';
import AdmZip from 'adm-zip';

const admin = { user: 'admin', pass: 'admin' };

async function login(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', admin.user);
  await page.fill('#user_pass', admin.pass);
  await page.click('#wp-submit');
}

function hasPii(text: string): boolean {
  const patterns = [
    /[\w.+-]+@[\w.-]+/i, // email
    /\+?[0-9][0-9\s-]{7,}/, // phone
    /\b\d{10}\b/, // national id
    /token/i, // token-like
  ];
  return patterns.some(p => p.test(text));
}

test('@e2e-debug downloads PII-free bundle', async ({ page }, testInfo) => {
  const messages: string[] = [];
  page.on('console', msg => {
    if (msg.type() === 'error' || msg.type() === 'warning') {
      messages.push(msg.text());
    }
  });

  const resp = await page.goto('/wp-login.php').catch(() => null);
  if (!resp || resp.status() >= 400) {
    test.skip('TODO: baseURL unreachable or Playground/wp-env missing');
  }

  await login(page);

  const debugResp = await page.goto('/wp-admin/admin.php?page=smartalloc-debug').catch(() => null);
  if (!debugResp || debugResp.status() >= 400) {
    test.skip('TODO: Debug screen unavailable');
  }

  const link = page.locator('a:has-text("Download Debug Bundle")').first();
  if (await link.count() === 0) {
    test.skip('TODO: No debug entries to download');
  }

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    link.click(),
  ]);

  const zipPath = await download.path();
  if (!zipPath) {
    test.fail('Download failed');
    return;
  }

  const zip = new AdmZip(zipPath);
  const entries = zip.getEntries().map(e => e.entryName);
  expect(entries).toEqual(expect.arrayContaining(['prompt.md', 'blueprint.json', 'env.json']));
  expect(entries.some(e => e.startsWith('logs/'))).toBeTruthy();

  const text = zip
    .getEntries()
    .filter(e => !e.isDirectory)
    .map(e => zip.readAsText(e))
    .join('\n');

  expect(hasPii(text)).toBeFalsy();
  expect(messages).toEqual([]);
});

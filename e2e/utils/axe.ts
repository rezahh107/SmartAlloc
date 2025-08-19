import { Page, expect } from '@playwright/test';

export async function injectAxe(page: Page) {
  await page.addScriptTag({ path: require.resolve('axe-core') });
}

export async function getViolations(page: Page) {
  await injectAxe(page);
  const results = await page.evaluate(async () => {
    // @ts-ignore
    return await axe.run();
  });
  return results.violations.filter((v: any) => v.impact === 'critical');
}

export async function expectNoCriticalViolations(page: Page) {
  const violations = await getViolations(page);
  if (violations.length) {
    console.error('Accessibility violations', JSON.stringify(violations, null, 2));
  }
  expect(violations).toEqual([]);
}

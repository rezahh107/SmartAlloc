import { test } from '@playwright/test';

test.describe('@e2e-i18n RTL admin basics', () => {
  test('admin pages render RTL layout', async ({ page }) => {
    test.skip(true, 'TODO: configure fa_IR locale and assert layout/keyboard order');
  });

  test('export round-trips RTL and emoji characters', async ({ page }) => {
    test.skip(true, 'TODO: seed data with RTL + emoji and verify CSV/XLSX export');
  });
});

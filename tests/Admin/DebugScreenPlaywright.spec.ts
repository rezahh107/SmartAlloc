import { test, expect } from '@playwright/test';

test.describe('Rule Engine Debug Widget', () => {
    test.beforeEach(async ({ page }) => {
        // Login as admin
        await page.goto('/wp-login.php');
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');

        // Navigate to debug screen
        await page.goto('/wp-admin/admin.php?page=smartalloc-debug');
    });

    test('widget renders evaluation form', async ({ page }) => {
        await expect(page.locator('h3:has-text("ارزیابی قوانین")')).toBeVisible();
        await expect(page.locator('input[name="entry_id"]')).toBeVisible();
        await expect(page.locator('button:has-text("اجرا")')).toBeVisible();
    });

    test('submit shows evaluation result', async ({ page }) => {
        await page.fill('input[name="entry_id"]', '123');
        await page.click('button:has-text("اجرا")');

        await expect(page.locator('#sa-evaluate-result')).toBeVisible();
        await expect(page.locator('#sa-evaluate-result')).toContainText('decision');
    });
});

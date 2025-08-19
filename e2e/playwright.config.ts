import { defineConfig } from '@playwright/test';

export default defineConfig({
  reporter: 'html',
  retries: 1,
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://localhost:8080',
    actionTimeout: 15000,
    navigationTimeout: 30000,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'on-first-retry',
  },
});

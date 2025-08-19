import { defineConfig } from '@playwright/test';

const baseURL = process.env.WP_BASE_URL || 'http://localhost:8080';

export default defineConfig({
  retries: 1,
  use: {
    baseURL,
    actionTimeout: 15000,
    navigationTimeout: 30000,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'on-first-retry',
  },
});

import { defineConfig } from '@playwright/test';

// Default to Playground port 9400; WP_BASE_URL overrides.
const baseURL = process.env.WP_BASE_URL || 'http://localhost:9400';

export default defineConfig({
  reporter: 'html',
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

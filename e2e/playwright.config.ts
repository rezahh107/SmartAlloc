import { defineConfig } from '@playwright/test';

// The Playground CLI path sets WP_BASE_URL to http://localhost:9400.
const baseURL = process.env.WP_BASE_URL || 'http://localhost:8080';

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

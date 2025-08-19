import { defineConfig } from '@playwright/test';
import path from 'path';

// Default to Playground port 9400; WP_BASE_URL overrides.
const baseURL = process.env.WP_BASE_URL || 'http://localhost:9400';
const downloadsPath = path.join(__dirname, 'downloads');

export default defineConfig({
  reporter: 'html',
  retries: 1,
  use: {
    baseURL,
    actionTimeout: 15000,
    navigationTimeout: 30000,
    screenshot: 'on',
    video: 'on',
    trace: 'on-first-retry',
    downloadsPath,
  },
});

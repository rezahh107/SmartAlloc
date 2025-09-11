import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e',
  testMatch: /example\.spec\.ts/,
  retries: 1,
  use: {
    baseURL: `http://localhost:${process.env.WP_PORT || 8080}`,
    timezoneId: 'UTC',
  },
  reporter: [
    ['list'],
    ['junit', { outputFile: 'build/junit.e2e.xml' }],
  ],
});

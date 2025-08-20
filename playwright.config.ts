import { defineConfig } from '@playwright/test';

// baseURL defaults to wp-env (http://localhost:8889)
// override via BASE_URL env var when running locally
export default defineConfig({
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost:8889',
  },
});

// run: E2E=1 npx playwright test

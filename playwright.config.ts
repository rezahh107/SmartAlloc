import { defineConfig } from '@playwright/test';
export default defineConfig({
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost:8889',
  },
  reporter: [['list']],
  timeout: 30000,
});

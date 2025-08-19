import { defineConfig } from '@playwright/test';
const baseURL = process.env.WP_BASE_URL || 'http://localhost:8080';
export default defineConfig({ use: { baseURL }, timeout: 60000 });

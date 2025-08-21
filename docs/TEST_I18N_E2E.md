# I18N E2E Smoke Test

An opt-in Playwright smoke test that exercises the UI in Persian (fa_IR) and RTL mode.

## How to run

```bash
E2E=1 E2E_I18N=1 npx playwright test tests/e2e/i18n-ui.spec.ts
```

The test will:

- Switch the site locale to `fa_IR` via WP-CLI (skips if WP-CLI is missing).
- Visit the SmartAlloc admin page and a Gravity Forms form page.
- Assert `<html dir="rtl">`, check for Persian text, and ensure no console errors.
- Capture screenshots under `artifacts/e2e/`.
- If `@axe-core/playwright` is installed, run an accessibility check; otherwise that step is skipped.

The test never fails CI; it only runs when both `E2E` and `E2E_I18N` are set to `1`.

# E2E (Opt-in) — Local Only

## Run with wp-env
1) `wp-env start`
2) Seed a GF form at `/contact-form/`.

## Playwright
- Install locally: `npx playwright install`
- Run (opt-in): `E2E=1 npx playwright test`

## Skips & CI
- By default, E2E tests SKIP (E2E!=1).
- If Playwright/wp-env or the form is missing → SKIP.
- CI stays green; no required jobs are added.

## k6 (manual)
- `k6 run tests/perf/k6/export-smoke.js` (manual only; never in CI).

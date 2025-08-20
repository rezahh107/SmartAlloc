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

## A11y (axe) — optional
- Install locally (optional): `npm i -D @axe-core/playwright`  
- Run: `E2E=1 E2E_A11Y=1 npx playwright test tests/e2e/a11y.spec.ts`
- Output: JSON snapshots under `artifacts/axe/` (ignored by git).
- If package/page is missing → SKIP.

## Block Editor smoke — optional
- Set credentials: `WP_USER=admin WP_PASS=admin`
- Run: `E2E=1 E2E_BLOCKS=1 npx playwright test tests/e2e/blocks.spec.ts`
- If editor/blocks not available → SKIP.

## Lighthouse (local only)
- Run (if you have Node): `bash scripts/lighthouse-local.sh http://localhost:8889`
- Produces HTML report under `artifacts/lighthouse/` (ignored by git).
- Not used in CI.

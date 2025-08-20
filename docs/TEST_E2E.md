# E2E Smoke Tests

Optional Playwright tests exercise the Gravity Forms contact form.

## Run locally

```bash
npx wp-env start    # boots WordPress at http://localhost:8889
E2E=1 npx playwright test
```

Set `BASE_URL` to point to a different site if needed.

## Skip behaviour

- If `E2E` is not `1`, tests are skipped.
- If Playwright is not installed, the spec does nothing.
- The smoke test checks for `/contact-form/`; when missing it calls `test.skip('form missing')`.

## Performance

`tests/perf/k6/export-smoke.js` is a manual k6 script for the export endpoint. CI never runs it.

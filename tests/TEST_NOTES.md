# Test Notes

Mapping to master checklist sections A–G.

| Section | Coverage |
| ------- | -------- |
| A. Authentication & Authorisation | Playwright `non-admin cannot access export page` ensures only administrators can export. |
| B. Input Validation | Playwright `empty range shows validation error` verifies date range validation. |
| C. Concurrency | `ParallelExportTest` spawns multiple processes to ensure unique export files under load. |
| D. Data Volume | `LargeDatasetTest` exports 10k rows and asserts memory ceiling. |
| E. Security Regression | Composer `test:security` suite and Psalm taint analysis run in CI. |
| F. Performance & Rate Limiting | `export-load.js` k6 script drives 50 concurrent requests; checks success or HTTP 429. |
| G. User Experience / Accessibility | Playwright `happy path generates file` confirms primary admin flow remains functional. |

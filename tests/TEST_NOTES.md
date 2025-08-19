# Test Notes

Mapping to master checklist sections A–G and numerics 3.x & 8.x.

| Section | Coverage |
| ------- | -------- |
| A. Authentication & Authorisation | Playwright `non-admin denied` blocks manual review access; `non-admin cannot access export page` ensures only administrators can export. |
| B. Input Validation | Playwright `empty range shows validation error` verifies date range validation; PHPUnit `invalid reason rejected` covers REST allowlist. |
| C. Concurrency | `ParallelExportTest` spawns multiple processes to ensure unique export files under load; PHPUnit `lock returns 409` checks manual review locking. |
| D. Data Volume | `LargeDatasetTest` exports 10k rows and asserts memory ceiling. |
| E. Security Regression | Composer `test:security` suite and Psalm taint analysis run in CI. |
| F. Performance & Rate Limiting | `export-load.js` k6 script drives 50 concurrent requests; checks success or HTTP 429. |
| G. User Experience / Accessibility | Playwright `approve flow shows success` validates admin notices and interactions. |
| 3.x Gravity Forms | PHPUnit scaffolds `ComplexFormTest` and `FlowPerksIntegrationTest` (marked SKIP with TODO) cover nested conditionals, uploads, multipage sessions and Flow/Perks routing. |
| 8.x Persian/RTL | `PersianRtlTest` and Playwright `@e2e-i18n` placeholders ensure RTL rendering, character handling and Jalali round-trip (SKIP with TODO). |
| Third-Party Compatibility | `JalaliFilterBypassTest` and Playwright `@e2e-compat` protect against Jalali date filters and Persian GF admin styles. |

## Quality Gates 2024

- Coding standards: WordPress-Core + WordPress-Extra (`composer cs`)
- Static analysis: PHPStan level 9 (`composer phpstan`) and Psalm
- Deprecations fail tests by default (`SA_FAIL_ON_DEPRECATION=0` to allow)
- Coverage gate: `composer coverage` enforces **MIN_COVERAGE=85** for ExporterService, Http/Rest and Compat namespaces (others TBD)

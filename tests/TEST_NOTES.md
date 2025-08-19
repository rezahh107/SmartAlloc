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
| 3.x Gravity Forms | `MultiPageUploadTest`, `NestedConditionalsTest`, `FlowRoutingTest`, `PerksComboTest` (SKIP if Brain Monkey/vfsStream/GF stubs missing) cover uploads, nested logic, routing and perks combos. |
| 8.x PHP 8.3 | `OverrideAttributeTest`, `TypedClassConstantsTest`, `JsonValidateTest`, `ReadonlyClassTest`, `DynamicConstFetchTest` verify new language features (SKIP on PHP <8.3 or missing functions). |
| 8.x Persian/RTL | `JalaliBypassTest` and `RTLLayoutTest` assert Jalali filter bypass and RTL data integrity (SKIP if PhpSpreadsheet/helper unavailable). |
| Third-Party Compatibility | `JalaliFilterBypassTest` and Playwright `@e2e-compat` protect against Jalali date filters and Persian GF admin styles. |
| Exporter/Importer Path | `MappingTest`, `NormalizerTest`, `PriorityRulesTest`, `LegacySheetTest` verify Excel mappings, normalizers and priority rules (SKIP if PhpSpreadsheet/vfsStream missing). |
| Prod-Risk A/B/C/D/E/G | `EnvLimitsTest`, `UnicodeAndCorruptionTest`, `ConcurrencyLiteTest` simulate env caps, unicode/corruption handling and idempotent locks (SKIP if env unknown or handlers absent). |
| Debug Kit | `ErrorCollectorTest` verifies redaction, breadcrumbs and SAVEQUERIES behaviour; `DebugIntegrationTest` covers nonce/capability checks and prompt context; `DebugKitTest` guards against PII leakage and ensures only sanitized prepared SQL is surfaced. `ReproBuilderTest` scaffolds repros, `DebugBundleIntegrationTest` downloads bundles and `DebugBundleSecurityTest` scans for PII (requires `SAVEQUERIES` for SQL samples). |
| Reports & Logs | `RedactionTest` masks mobile/national_id/postal_code; `CorrelationIdTest` checks request id propagation and health hashing; `AdminReportTest` renders metrics without leaking PII (SKIP if Brain Monkey/helpers missing). |
| Chaos/Resilience | `ReproBuilderTest` and `DebugBundleIntegrationTest` validate reproducible scaffolds and admin/CLI flows. |
| GF/i18n | Repro blueprints and tests remain locale-neutral and RTL-safe. |
| Repro Hardening | Bundles stay under 1MB and PII-free; blueprint schema, nonce and capability checks are validated. |

These tests do not modify runtime code. If a prerequisite such as Brain Monkey, vfsStream or PhpSpreadsheet is missing, the affected tests call `markTestSkipped()` with a clear TODO instead of failing.

## Quality Gates 2024

- Coding standards: WordPress-Core + WordPress-Extra (`composer cs`)
- Static analysis: PHPStan level 9 (`composer phpstan`) and Psalm
- Deprecations fail tests by default (`SA_FAIL_ON_DEPRECATION=0` to allow)
- Coverage gate: `composer coverage` enforces **MIN_COVERAGE=85** for ExporterService, Http/Rest and Compat namespaces (others TBD)

## Conflict Cleanup & Discovery

- `NoConflictMarkersTest` fails if merge conflict markers remain in the tree.
- PHP 8.3 feature tests skip on older runtimes using `PHP_VERSION_ID`/`function_exists` guards.
- PHPUnit discovery remains default; no additional paths were required.

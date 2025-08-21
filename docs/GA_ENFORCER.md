# GA Enforcer

The GA Enforcer evaluates QA artifacts and release signals against strict thresholds.
By default it runs in advisory mode and never blocks the build. When enforcement is
explicitly enabled it exits with a non‑zero status if any signal exceeds the
configured limits.

## Advisory vs enforce

- **Advisory** – default behaviour. Always exits `0` and records counts/warnings.
- **Enforce** – enabled with `RUN_ENFORCE=1` or the `--enforce` flag. Exits `1`
  when a threshold is violated or version mismatch is detected.

## Thresholds

Thresholds are resolved with the following precedence:

1. Baseline `scripts/.ga-enforce.json`.
2. Profile selected via `--profile` (`rc`, `ga` or path).
3. CLI flags overriding individual keys.

Missing keys fall back to these internal defaults:

```json
{
  "rest_permission_violations": 0,
  "sql_prepare_violations": 0,
  "secrets_findings": 0,
  "license_denied": 0,
  "i18n_domain_mismatches": 0,
  "coverage_min_lines_pct": 0,
  "require_manifest": true,
  "require_sbom": true,
  "version_mismatch_fatal": true,
  "pot_min_entries": 10,
  "dist_audit_max_errors": 0,
  "wporg_lint_max_warnings": 0
}
```

Edit the JSON file or supply a profile/CLI flag to override any limit.

## Quick start

```bash
# advisory
php scripts/ga-enforcer.php --profile=rc

# enforce RC thresholds
RUN_ENFORCE=1 php scripts/ga-enforcer.php --profile=rc --enforce --junit

# enforce GA thresholds
RUN_ENFORCE=1 php scripts/ga-enforcer.php --profile=ga --enforce --junit
```

The `--junit` flag writes `artifacts/ga/GA_ENFORCER.junit.xml` with one
`<testcase>` per signal and a `<failure>` node when that signal exceeds its
threshold.

## Coverage Import

`scripts/coverage-import.php` normalizes coverage reports. It looks for
`artifacts/coverage/clover.xml` first and falls back to an existing
`coverage.json`. The importer emits a deterministic
`artifacts/coverage/coverage.json` with totals, covered lines and percentage
and is invoked automatically by the GA Enforcer when needed.

## Schema Validation (Advisory)

`scripts/artifact-schema-validate.php` inspects optional QA artifacts for basic
shape and presence. Any mismatches are recorded as schema warnings and are
advisory by default.

## Advisory CI Example

`docs/ci-examples/ga-enforcer-advisory.yml` shows a minimal GitHub Actions job
that installs dependencies, imports coverage and runs the GA Enforcer in
advisory mode. Teams can copy this into their own CI when ready. The enforcer
continues to exit `0` unless `--enforce` or `RUN_ENFORCE=1` is supplied.

## QA Plan mapping

| QA Plan stage | Artifact/Signal |
| ------------- | ---------------- |
| 2 | REST/SQL/Secrets/License scans |
| 3 | `artifacts/dist/manifest.json`, `sbom.json`, dist-audit |
| 4 | `scripts/version-coherence.php` |
| 7 | `artifacts/i18n/pot-refresh.json` |
| 9 | Coverage reports |
| 14 | `artifacts/ga/GA_ENFORCER.{json,txt,junit.xml}` |

## Coverage import

`php scripts/coverage-import.php` normalizes Clover XML or existing coverage JSON into `artifacts/coverage/coverage.json`. The GA Enforcer will invoke it automatically if coverage is missing.

## Schema validation

`php scripts/artifact-schema-validate.php` scans `artifacts/` for malformed JSON. Warnings are recorded in `artifacts/schema/schema-validate.json` and surfaced by the GA Enforcer (TXT/JSON) and as `Artifacts.Schema` in JUnit.

## Quick start

```bash
php scripts/coverage-import.php
php scripts/artifact-schema-validate.php
php scripts/ga-enforcer.php --profile=rc --junit
RUN_ENFORCE=1 php scripts/ga-enforcer.php --profile=ga --enforce --junit
```

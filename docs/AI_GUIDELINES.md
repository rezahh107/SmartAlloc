# AI Guidelines

This repository uses several generated artifacts. When updating tooling or
writing tests, keep the following expectations in mind:

## Coverage import

Coverage is normalised to `artifacts/coverage/coverage.json` via
`scripts/coverage-import.php`. The search order is:

1. `COVERAGE_INPUT` (if set)
2. `artifacts/coverage/clover.xml`
3. `coverage/clover.xml`
4. `clover.xml`
5. `artifacts/coverage/coverage.json`
6. `coverage.json`

The first existing file is consumed. The output lists totals and per-file
metrics sorted by path with percentages rounded to one decimal place.

## Distribution manifest

`artifacts/dist/manifest.json` is canonical. It must contain an `entries` array
where each object includes:

```json
{ "path": "file.php", "sha256": "<64-hex>", "size": 123 }
```

`artifact-schema-validate.php` only inspects this manifest. A legacy `files[]`
key triggers an advisory warning; missing or malformed manifests also raise
warnings.

## GA Enforcer JUnit

`scripts/ga-enforcer.php` runs `coverage-import.php` and
`artifact-schema-validate.php` automatically. Its JUnit report always contains a
`testcase` named `Artifacts.Schema`. Advisory runs (e.g. `--profile=rc`) mark it
skipped; GA enforcement (`--profile=ga --enforce`) fails the testcase when
schema warnings exceed configured thresholds.

Before committing tooling or tests, run the SQL prepare scanner locally:

```bash
php scripts/scan-sql-prepare.php
```

Review and justify any allowlist entries in `tools/sql-allowlist.json`.


## Allocation Engine

Scoring uses weights filterable through `smartalloc_scoring_weights`.
All queued jobs must remain idempotent by guarding with dedupe keys when
scheduling notifications or background tasks.

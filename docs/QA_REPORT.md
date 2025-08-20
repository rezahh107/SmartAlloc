# QA Report

The project includes a helper script for generating an overview of test status.

## Generating the report

Run:

```
php scripts/qa-report.php
```

This writes `qa-report.json` and `qa-report.html` in the repository root. The script never exits with a non-zero status and notes missing artifacts such as code coverage.

## Interpreting the report

`qa-report.json` contains:

- `coverage_percent` – overall coverage percentage from `coverage-unit/index.xml` when available.
- `env` – state of `RUN_SECURITY_TESTS`, `RUN_PERFORMANCE_TESTS` and `E2E` environment variables.
- `test_files` – number of `*Test.php` files under `tests/`.
- `notes` – any warnings about missing data.

The HTML version renders the same information in a simple right-to-left layout for RTL readers.

# QA Report

The project includes helper scripts for generating an overview of test status and scanning REST permissions.

## Generating the report

Run:

```
php scripts/qa-report.php
```

This writes `qa-report.json` and `qa-report.html` in the repository root. The script never exits with a non-zero status and notes missing artifacts such as code coverage.

To scan REST routes for insecure or missing permission callbacks, run:

```
php scripts/scan-rest-permissions.php > rest-violations.json
```

The scanner prints a JSON array of files and always exits with code `0`.

To automatically run the scan before pushing, install the sample pre-push hook:

```
ln -sf ../../scripts/git-hooks/pre-push.sample .git/hooks/pre-push
```

## Interpreting the report

`qa-report.json` contains:

- `coverage_percent` – overall coverage percentage from `coverage-unit/index.xml` when available.
- `env` – state of `RUN_SECURITY_TESTS`, `RUN_PERFORMANCE_TESTS` and `E2E` environment variables.
- `test_files` – number of `*Test.php` files under `tests/`.
- `rest_permission_violations` – count of insecure REST route permissions when the scanner is available.
- `notes` – any warnings about missing data.

The HTML version renders the same information in a simple right-to-left layout for RTL readers.

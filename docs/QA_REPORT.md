# QA Report

The project includes helper scripts for generating an overview of test status and scanning REST permissions.
An additional SQL-prepare scanner is available for spotting unprepared `$wpdb` calls.

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

To scan for unprepared SQL queries, run:

```
php scripts/scan-sql-prepare.php > sql-violations.json
```

The SQL scanner prints a JSON array and always exits with code `0`.

To automatically run the SQL scanner before committing, install the sample pre-commit hook:

```
ln -sf ../../scripts/git-hooks/pre-commit.sample .git/hooks/pre-commit
```

To scan for leaked secrets, run:

```
php scripts/scan-secrets.php > secrets.json
```

The tool emits a JSON array of findings with `file`, `line`, `type`, and `snippet` fields. Investigate any reported secrets and allowlist intentional ones with `@security-ok-secret`.

To audit Composer licenses, run:

```
php scripts/license-audit.php > licenses.json
```

The audit prints JSON containing a `summary` (`total`, `unknown`, `denied`) and a detailed `packages` list. Review packages with unknown or denied licenses and resolve issues before release.

To bundle QA artifacts, run:

```
php scripts/qa-bundle.php
```

This writes `artifacts/qa/qa-bundle.zip` with available reports and the latest Axe/Lighthouse outputs for easy sharing.

## Interpreting the report

`qa-report.json` contains:

- `coverage_percent` – overall coverage percentage from `coverage-unit/index.xml` when available.
- `env` – state of `RUN_SECURITY_TESTS`, `RUN_PERFORMANCE_TESTS` and `E2E` environment variables.
- `test_files` – number of `*Test.php` files under `tests/`.
- `rest_permission_violations` – count of insecure REST route permissions when the scanner is available.
- `sql_prepare_violations` – count of unprepared `$wpdb` calls when the scanner is available.
- `notes` – any warnings about missing data.

The HTML version renders the same information in a simple right-to-left layout for RTL readers.

# GA Enforcer

The GA Enforcer evaluates QA artifacts and release signals against configurable
thresholds. It emits JSON/TXT/JUnit summaries under `artifacts/ga/` and only
fails a build when enforcement is explicitly enabled.

## Coverage Import

`scripts/coverage-import.php` normalises coverage reports into
`artifacts/coverage/coverage.json`. Set `COVERAGE_INPUT` to override the
search path.

**Search order**

1. `COVERAGE_INPUT` (if set)
2. `artifacts/coverage/clover.xml`
3. `coverage.xml`
4. `clover.xml`
5. `artifacts/coverage/coverage.json`
6. `coverage.json`

The first existing file is consumed. Clover XML is parsed with
`SimpleXMLElement`; JSON is re‑emitted. The output schema is:

```json
{
  "source": "clover|json|none",
  "generated_at": "ISO8601",
  "totals": { "lines_total": 0, "lines_covered": 0, "pct": 0.0 },
  "files": [
    { "path": "relative/path.php", "lines_total": 0, "lines_covered": 0, "pct": 0.0 }
  ]
}
```

Files are sorted by path and percentages are rounded to two decimals for
determinism. If no input is found the script writes a zeroed document with
`"source": "none"`.

## Artifact Schema Validation

`scripts/artifact-schema-validate.php` scans for malformed or incomplete JSON
artifacts. It inspects, when present:

* `artifacts/coverage/coverage.json`
* `artifacts/qa/*.json`
* `artifacts/dist/*.json`
* `artifacts/i18n/*.json`

Each file is decoded and basic fields are verified (e.g. `totals.pct` in
coverage, `entries` in a dist manifest). Results are written to
`artifacts/schema/schema-validate.json`:

```json
{ "warnings": 0, "items": [ { "path": "...", "issue": "Missing field", "field": "totals.pct" } ] }
```

This validator is advisory; warnings do not fail the build unless GA Enforcer is
run in enforce mode with a `schema_warnings` threshold.

## Quick start

```bash
php scripts/coverage-import.php
php scripts/artifact-schema-validate.php
php scripts/ga-enforcer.php --profile=rc --junit
RUN_ENFORCE=1 php scripts/ga-enforcer.php --profile=ga --enforce --junit
```

## GitHub Actions (advisory)

```yaml
name: Advisory GA Enforcer
on: [workflow_dispatch]
jobs:
  enforcer:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', tools: composer }
      - run: composer install --no-interaction --prefer-dist
      - run: php scripts/coverage-import.php
      - run: php scripts/artifact-schema-validate.php
      - run: php scripts/ga-enforcer.php --profile=rc --junit
      - uses: actions/upload-artifact@v4
        with:
          name: ga-enforcer
          path: |
            artifacts/coverage/coverage.json
            artifacts/schema/schema-validate.json
            artifacts/ga/GA_ENFORCER.*
```

The job above runs the enforcer in advisory mode and uploads the generated
artifacts for inspection.


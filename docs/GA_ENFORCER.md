# GA Enforcer

The GA Enforcer evaluates QA artifacts and release signals against configurable
thresholds. It emits JSON/TXT/JUnit summaries under `artifacts/ga/` and only
fails a build when enforcement is explicitly enabled.

## Rehearsal flow

`scripts/ga-rehearsal.sh` runs all scanners in advisory mode. Each step is
skip‑safe; missing tools simply mark the step skipped. A short summary is
written to `artifacts/ga/GA_REHEARSAL.txt` with a matching JUnit file
`artifacts/ga/GA_REHEARSAL.junit.xml` containing a top‑level testcase
`GA.Rehearsal` marked skipped. Composer exposes this via `composer qa:advisory`.

## Coverage Import

`scripts/coverage-import.php` normalises coverage reports into
`artifacts/coverage/coverage.json`. GA Enforcer invokes this script
automatically before evaluating coverage signals. Set `COVERAGE_INPUT` to
override the search path.

**Search order**

1. `COVERAGE_INPUT` (if set)
2. `artifacts/coverage/clover.xml`
3. `coverage/clover.xml`
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

Files are sorted by path and percentages are rounded to one decimal for
determinism. If no input is found the script writes a zeroed document with
`"source": "none"`.

## Dist Manifest

`scripts/dist-manifest.php` writes `artifacts/dist/manifest.json` containing a
canonical `entries` array. The schema validator requires this manifest to
exist. Each entry is sorted by path and includes:

```json
{ "path": "file.php", "sha256": "...", "size": 123 }
```

Legacy fields may appear for backwards compatibility, but `entries` is the
source of truth for consumers. If a legacy `files[]` array exists the schema
validator emits an advisory warning:

```
legacy files[] present; use entries[] as canonical
```

## Artifact Schema Validation

`scripts/artifact-schema-validate.php` validates only the distribution manifest
under `artifacts/dist/manifest.json`. The manifest must contain a canonical
`entries[]` array where each object includes a `path`, 64‑character `sha256`, and
integer `size`. Missing manifests, empty or malformed `entries`, or a legacy
`files[]` array produce advisory warnings. All warnings are sorted for
deterministic output in `artifacts/schema/schema-validate.json`:

```json
{
  "warnings": [
    {"file":"path/to.json","reason":"missing totals.pct"}
  ],
  "count": 1
}
```

The validator is advisory and always exits zero. GA Enforcer consumes the
warning count and may enforce thresholds when run with `--enforce`. Its JUnit
report always includes a testcase `Artifacts.Schema`; advisory runs mark it
skipped, and GA enforcement fails the testcase when schema warnings exceed the
configured threshold.

GA Enforcer also emits a testcase `Dist.Manifest`. It aggregates warnings from
the manifest, version coherence and readme linting steps. RC/advisory runs mark
the testcase skipped. Under `--profile=ga --enforce` the testcase fails when
`dist_manifest_warnings` (default `0`) is exceeded.

Additional testcases:

- `I18N.Lint` – output from `scripts/i18n-lint.php` highlighting text-domain or
  placeholder issues.
- `WPOrg.Preflight` – results of `scripts/wporg-deploy-checklist.php` ensuring
  required assets and a matching Stable tag.

Both are skipped in RC runs and enforced only when GA mode is used with
`--enforce`.

The SQL prepare scanner integrates similarly. GA Enforcer runs
`scan-sql-prepare.php` automatically and emits a testcase `SQL.Prepare` in its
JUnit output. Advisory and RC profiles mark the testcase skipped with a message
containing the violation and allowlist counts. When run with
`--profile=ga --enforce`, any non‑allowlisted violations cause the testcase to
fail and the first few `file:line` locations are included in the failure
message.

The REST permission scanner (`scan-rest-permissions.php`) is also invoked
automatically. Its JUnit testcase `REST.Permissions` is skipped in RC/advisory
runs and fails under `--profile=ga --enforce` when mutating routes have any
warnings or when read‑only warnings exceed the configured
`rest_permission_violations` threshold.

### Profiles

* RC: `coverage_pct_min` 60, `schema_warnings` ≤ 3
* GA: `coverage_pct_min` 80, `schema_warnings` 0

Local overrides may be provided in `configs/ga-profiles.local.yaml` using a
simple profile map:

```yaml
rc:
  coverage_pct_min: 55
ga:
  schema_warnings: 1
```

The file is optional and ignored by git. Values override the built‑in profile
JSON thresholds.

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


## CI quick start

### Local advisory
```bash
php scripts/coverage-import.php
php scripts/artifact-schema-validate.php
php scripts/ga-enforcer.php --profile=rc --junit
```

### Manual enforce (GA threshold)
```bash
RUN_ENFORCE=1 php scripts/ga-enforcer.php --profile=ga --enforce --junit
```

See `.github/workflows/qa-advisory.yml` for advisory signals and `.github/workflows/ga-enforce.yml` for manual enforcement.

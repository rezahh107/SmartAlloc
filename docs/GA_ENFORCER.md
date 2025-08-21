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

Thresholds are read from `scripts/.ga-enforce.json`. If the file is missing the
following defaults are used:

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
  "version_mismatch_fatal": true
}
```

Edit the JSON file to override any limit.

## Quick start

```bash
php scripts/ga-enforcer.php                        # advisory, exit 0
RUN_ENFORCE=1 php scripts/ga-enforcer.php --enforce # enforce thresholds
RUN_ENFORCE=1 vendor/bin/phpunit --filter GAEnforcerTest
```

After running `scripts/ga-rehearsal.sh` you can run the enforcer with
`RUN_ENFORCE=1` to make the RC/GA decision.

## QA Plan mapping

| QA Plan stage | Artifact/Signal |
| ------------- | ---------------- |
| 2 | REST/SQL/Secrets/License scans |
| 3 | `artifacts/dist/manifest.json` & `sbom.json` |
| 4 | `scripts/version-coherence.php` |
| 7 | `artifacts/i18n/pot-refresh.json` |
| 9 | `artifacts/qa/qa-report.json` |
| 14 | `artifacts/ga/GA_ENFORCER.{json,txt}` |

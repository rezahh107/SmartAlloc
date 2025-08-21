# Release Gate (Advisory)

Run the QA helpers and review the generated artifacts before releasing. This gate is non-blocking and CI stays green by default.

## Checklist

| QA Plan (Critical/High) | Artifact | Notes |
| --- | --- | --- |
| REST guard | `rest-violations.json` | should be empty or allowlisted |
| SQL prepare guard | `sql-violations.json` | review for unprepared queries |
| Secrets scan | `secrets.json` | manual review |
| License audit | `licenses.json` | denylist must be empty |
| Version coherence & readme | stdout | run validators; expect no mismatches |
| Coverage (>=80%) | `qa-report.json` | includes coverage% if available |
| Schema warnings | `artifact-schema-validate` | must be zero for GA |
| Perf opt-in | `AllocationPerformanceTest` | check p95/memory |

## How to run

```bash
bash scripts/qa-orchestrator.sh
php scripts/version-coherence.php
php scripts/validate-readme.php
php scripts/sbom-from-composer.php
```

Validators should report no mismatches or warnings, and the SBOM file should be generated for GA.

## What to upload

```
artifacts/qa/qa-bundle.zip
```

All steps are advisory and may be skipped; missing tools are ignored.

## Packaging

Verify distribution artifacts before release:

```bash
php scripts/dist-audit.php [path]
php scripts/dist-manifest.php [path]
```

`dist-audit` emits JSON with any violations (e.g., dev files or oversized assets). Review the list; minor warnings may pass for GA, but serious issues should be fixed before shipping. `dist-manifest` records checksums and sizes for auditing.

## SBOM

Generate a minimal SBOM from `composer.lock`:

```bash
php scripts/sbom-from-composer.php
```

Expect `artifacts/dist/sbom.json` to exist for GA.

## GO/NO-GO & Tag Preflight

Run final advisory helpers to summarize QA signals and preview tagging:

```bash
php scripts/go-no-go.php
php scripts/changelog-guard.php
php scripts/tag-preflight.php
```

`go-no-go.php` aggregates any existing QA artifacts and writes `artifacts/qa/go-no-go.html` for a quick RTL review. `changelog-guard.php` checks that the top `CHANGELOG.md` entry aligns with the plugin version and readme stable tag. `tag-preflight.php` prints a release note stub and reports SHA256 hashes for `artifacts/dist/manifest.json` and `artifacts/dist/sbom.json` if present.

All outputs are advisory and non-blocking; missing files are skipped.

## Finalization

Generate release notes, snapshot the final checklist, and preview tagging:

```bash
php scripts/release-notes.php
php scripts/final-checklist.php
bash scripts/tag-dry-run.sh
```

All steps are advisory and non-blocking.

## Runbooks

- Rehearsal: `bash scripts/ga-rehearsal.sh`
- Finalize: `bash scripts/release-finalizer.sh`

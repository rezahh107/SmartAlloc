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
| Coverage (optional) | `qa-report.json` | includes coverage% if available |
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

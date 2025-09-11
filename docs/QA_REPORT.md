# QA Report

`qa-report.php` aggregates coverage, schema validation, REST permission scan and
SQL prepare results.

## Running

```bash
php scripts/qa-report.php
```

The script writes `artifacts/qa/qa-report.json` and `artifacts/qa/qa-report.html`
and never exits with a non‑zero status.

## JSON schema

```json
{
  "generated_at_utc": "ISO8601",
  "summary": {
    "coverage_pct": 0,
    "schema_warnings": 0,
    "rest_permissions": {
      "routes": 0,
      "mutating_warnings": 0,
      "readonly_warnings": 0
    },
    "sql_prepare": {"violations": 0, "allowlisted": 0}
  },
  "notes": ["..."]
}
```

Arrays and object keys are sorted for determinism. The HTML variant renders the
same information in a basic RTL layout.

## Phase 1R2 QA Summary

- Security: PASS
- WP Standards: PASS
- Testing: SANITY-PASS
- Performance: N/A (Phase 1)
- Site Health: GREEN-BY-CONFIG
- Patch Guard: PASS

- Dispatch: failed (401), switched to workflow_dispatch fallback

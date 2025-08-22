# Reports & Metrics

## QA Report

`scripts/qa-report.php` aggregates the following signals and emits both JSON and RTL-friendly HTML:

- Artifact schema warnings
- Code coverage percentage
- SQL prepare scanner results
- REST permission warnings
- Secret scan results
- HTTP header guard results
- License audit summary
- Exporter validation counts
- Form150 validation rule violations

Output is deterministic and always includes `timestamp_utc`.

### Sample JSON

```json
{
  "timestamp_utc": "2025-01-01T00:00:00Z",
  "summary": {
    "coverage_pct": 92.5,
    "schema_warnings": 1,
    "rest_permissions": { "routes": 5, "mutating_warnings": 0, "readonly_warnings": 1 },
    "sql_prepare": { "violations": 0, "allowlisted": 0 },
    "secrets": { "violations": 0, "allowlisted": 0 },
    "headers": { "missing": 0, "allowlisted": 0 },
    "license": { "unapproved": 0 },
    "exporter": { "errors": 0, "warnings": 0 },
    "validation": {
      "national_id_checksum": 0,
      "mobile_prefix_09": 1,
      "landline_eq_mobile": 0,
      "duplicate_liaison_phone": 0,
      "postal_code_fuzzy": {"accept": 10, "manual": 2, "reject": 1},
      "hikmat_tracking_sentinel": 0
    }
  },
  "notes": []
}
```

## REST Metrics

`scripts/metrics-endpoints.php` registers read-only endpoints under `/smartalloc/v1/metrics`.

- Capability required: `manage_smartalloc`
- Response is deterministic and sorted
- Metrics include:
  - Allocation counts (total, by mentor, by center – capacity limited to 60)
  - Export counts (total and error counts)
  - Validation errors grouped by rule and field
  - Dead-letter queue backlog statistics

## Site Health Checks

`SmartAlloc\Services\HealthCheckService` hooks into WordPress Site Health and reports:

- Database connectivity
- Redis/queue availability
- Dead-letter queue backlog
- Mentor capacity utilisation (flags mentors with assigned > 60)

## Validation Rule Mapping

The QA report and metrics map to SmartAlloc execution document rules:

| Rule | Description |
| --- | --- |
| national_id_checksum | Iranian national code checksum |
| mobile_prefix_09 | Mobile numbers must start with 09 and be 11 digits |
| landline_eq_mobile | Landline must not equal mobile |
| duplicate_liaison_phone | Removes liaison duplicate when field 23 equals field 21 |
| postal_code_fuzzy | Postal code fuzzy matching (≥0.90 accept, 0.80–0.89 manual, <0.80 reject) |
| hikmat_tracking_sentinel | حكمة tracking sentinel `1111111111111111` |

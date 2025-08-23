# SmartAlloc Operations Runbook

This runbook covers day‑to‑day operational tasks for the SmartAlloc plugin.

## Backup and Restore

Each form uses dedicated tables named `wp_smartalloc_*_f{formId}`. Back up the database before upgrades:

```bash
mysqldump $DB_NAME wp_smartalloc_% > smartalloc-backup.sql
```

To restore, import the dump and flush caches:

```bash
mysql $DB_NAME < smartalloc-backup.sql
wp cache flush
```

## Smoke Checks

1. `wp smartalloc smoke:env` – verifies environment versions and capability.
2. `wp smartalloc smoke:allocate --students=10 --mentors=5 --dry-run` – ensures allocation logic runs.
3. Visit `/wp-json/smartalloc/v1/health` – REST health endpoint must return HTTP 200.

## Log Redaction

The logger masks email addresses, mobile numbers, and national IDs. Operators should never see raw PII in log files. Logs are written in UTC.

## Metrics and Alerts

Metrics are labeled by `form_id` and exposed via `smartalloc_metrics`. Recommended alerts:

- High allocation failures (>5% in 5 minutes)
- Export queue length above 100
- Database connectivity errors

## Escalation

1. Collect logs and the output of `wp smartalloc smoke:env`.
2. Create an incident ticket with the findings.
3. Escalate to the engineering on‑call channel.

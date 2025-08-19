# Observability

SmartAlloc exposes health and metrics endpoints for monitoring.

## Metrics
- REST endpoint: `/wp-json/smartalloc/v1/metrics`
- WP-CLI: `wp smartalloc metrics`

### Metrics snapshots
Appending `?snapshot=1` to the metrics endpoint returns a point-in-time view of
counters without resetting them. The Metrics/Circuit Breaker test suite exercises
this snapshot mode during release checks.

## Logs
Application logs are written using WordPress's logging facilities. Configure log retention via the Admin Guide.

### Correlation IDs
All requests and CLI operations emit an `X-SmartAlloc-Correlation-ID` header and
record the same value in logs. The Prod-Risk tests ensure IDs propagate across
HTTP and background jobs for end-to-end traceability.

### Redaction tests
Reports & Logs tests verify that logs and metrics redact PII (email addresses,
student identifiers) before data is stored or transmitted.

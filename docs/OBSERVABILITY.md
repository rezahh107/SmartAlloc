# Observability

SmartAlloc exposes lightweight metrics for export operations. The `MetricsCollector` service stores counters, gauges and recent timing samples in a WordPress option and powers the `/smartalloc/v1/metrics` endpoint.

## Metrics

| Metric | Type | Description |
| --- | --- | --- |
| `exports_total` | counter | Successful export generations |
| `exports_failed` | counter | Failed export attempts |
| `locks_hit` | counter | Attempts blocked by an in-progress export |
| `rate_limit_hit` | counter | Requests rejected by rate limiting |
| `retention_pruned` | counter | Files removed by retention cron |
| `stale_files` | counter | Exports flagged missing or stale |
| `checksum_mismatch` | counter | Files with checksum mismatches |
| `breaker_open` | counter | Circuit breaker opened |
| `exports_in_progress` | gauge | Currently running exports |
| `export_duration_ms` | timing | Recent export durations in ms (last 10 samples) |

The metrics endpoint returns a JSON snapshot:

```json
{
  "counters": {"exports_total": 3},
  "gauges": {"exports_in_progress": 1},
  "timings": {"export_duration_ms": [150]},
  "ts": 1700000000
}
```

Results are cached for 60 seconds via `get_transient` to avoid hot paths.

## Circuit Breaker

The `CircuitBreaker` tracks consecutive export failures. Five failures within two minutes open the breaker for five minutes. While open, the export REST endpoint returns `503` with a `Retry-After` header and the admin export page displays a warning banner.

## Admin Snapshot

The Export page surfaces a small metrics summary showing total exports and stale files, helping operators spot issues quickly without exposing any PII.

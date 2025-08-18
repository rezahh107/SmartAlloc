# SmartAlloc Operator Guide

This guide covers day to day operations.

## Manual review
Use the SmartAlloc admin menu to approve or reject allocations. Nonces and
capability checks protect all actions.

## Exports
Exports are stored under the WordPress uploads directory and are cleaned up by
a daily cron task based on the **export_retention_days** setting.

## Reports and metrics
The `/smartalloc/v1/metrics` endpoint aggregates allocation statistics. Results
are cached for a short period (configurable via **metrics_cache_ttl**).

## CLI usage
```
wp smartalloc export --from=2024-01-01 --to=2024-01-31 --format=json
wp smartalloc allocate --entry=123 --mode=direct
wp smartalloc review --approve=123 --mentor=10
```
Each command respects `--format=json` and exits non–zero on failure.

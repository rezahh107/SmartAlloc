# SmartAlloc Compatibility Matrix

| Component | Minimum | Recommended | Notes |
|-----------|---------|-------------|-------|
| WordPress | 6.3 | 6.4 | Multisite supported; network‑wide activation recommended. |
| PHP | 8.1 | 8.2 | Built and tested with OPcache enabled. |
| Gravity Forms | 2.7 | 2.7+ | Requires Pro license for database access. |
| MySQL | 8.0 | 8.0+ | Uses InnoDB tables with utf8mb4 encoding. |

## Multisite

When network‑activated, the plugin stores data per site. Each site requires its own configuration. Allocation tables remain isolated.

## Large Dataset Guidance

For forms exceeding 10k students, enable object caching and configure the performance flags in `wp-config.php`:

```php
define( 'SMARTALLOC_PERF_CHUNK', 500 );
```

Run allocations during off‑peak hours and monitor database load.

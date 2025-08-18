# SmartAlloc API

Base path: `/wp-json/smartalloc/v1`

## /allocate (POST)
Allocate a Gravity Forms entry. Requires authentication and capability
`manage_smartalloc`.

## /health (GET)
Returns database and cache status. Response includes version and current time.

## /metrics (GET)
Aggregated allocation metrics. Supports `date_from`, `date_to`, `group_by` and
is cached for a short period. Date ranges over 90 days are rejected.

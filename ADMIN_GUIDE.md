# Admin Guide

This guide covers routine operational tasks for SmartAlloc.

## Updating Export Config
1. Upload the JSON config to `wp-content/uploads/SmartAlloc_Exporter_Config_v1.json`.
2. Flush caches via `wp smartalloc cache flush`.

## User Roles
- `manage_smartalloc` capability grants access to all plugin features.

## Logs and Health
- View health status at `/wp-json/smartalloc/v1/health`.
- Metrics are available via CLI: `wp smartalloc metrics`.

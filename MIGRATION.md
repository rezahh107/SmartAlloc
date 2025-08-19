# Migration Guide

Use this guide when upgrading SmartAlloc.

## Database
Run pending migrations after updating:
```bash
wp smartalloc upgrade
```

## Configuration
Review new options in `wp smartalloc settings list` and update as needed.

## Skip conditions
Migrations are automatically skipped when the legacy allocation sheet `9394`
is present or when the database is in read-only mode. In those cases the plugin
retains the pre-existing schema for backward compatibility. The Prod-Risk test
suite covers these skip paths so administrators can verify rollbacks without
data loss.

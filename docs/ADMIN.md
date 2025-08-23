# SmartAlloc Admin Guide

This guide describes how to operate the SmartAlloc plugin inside the WordPress admin panel.

## Roles and Capabilities

Only users with the `manage_smartalloc` capability can access SmartAlloc screens. The capability can be granted via a role editor or directly with `wp cap add`.

## Menu Map

| Menu Item | Path | Notes |
|-----------|------|-------|
| Import | SmartAlloc → Import | Upload student or mentor data files. |
| Students (GF) | SmartAlloc → Students | Links to the Gravity Forms entry list for the active form. |
| Allocation – Auto | SmartAlloc → Allocation → Auto | Runs automatic allocation for all unassigned students. |
| Allocation – Manual | SmartAlloc → Allocation → Manual | Allows administrators to pick a mentor manually. |
| Allocation – Dry‑Run | SmartAlloc → Allocation → Dry‑Run | Executes the algorithm without persisting results. |
| Export | SmartAlloc → Export | Generates an export based on the current configuration. |
| Reports | SmartAlloc → Reports | Aggregated metrics per form. |
| Settings | SmartAlloc → Settings | Global and per‑form configuration. |
| Logs | SmartAlloc → Logs | Recent activity with PII fields masked. |

## Nonce Flows

All state‑changing actions are protected with nonces created via `wp_create_nonce( 'smartalloc' )`. Requests must include the nonce in a `_wpnonce` parameter and are validated with `wp_verify_nonce()` before processing.

## Error Codes

| Code | Meaning |
|------|---------|
| SA001 | Missing `manage_smartalloc` capability. |
| SA002 | Nonce verification failed. |
| SA003 | Invalid input detected by the validator. |
| SA004 | Database operation rejected. |

Error codes are returned in REST responses and rendered in admin notices.

## Safe Rollback

1. **Deactivate** – Use the WordPress Plugins screen or run `wp plugin deactivate smart-alloc`.
2. **Restore Database** – Import the backup of the `wp_smartalloc_*` tables for the affected forms.
3. **Restore Files** – Replace the plugin directory with the previous release.
4. **Reactivate** – Run `wp plugin activate smart-alloc` and verify with the smoke tests.

Always keep backups for each form before applying upgrades.

<?php
// Return an array of option/transient keys that uninstall.php is expected to delete.
// Maintain as test-only truth; empty array means SKIP (keeps CI green until filled).
return [
    'smartalloc_form_id',
    'smartalloc_form_id_updated_at',
    'smartalloc_settings',
    'smartalloc_version',
    'smartalloc_last_migration',
    'smartalloc_debug_enabled',
    'smartalloc_debug_errors',
    'smartalloc_purge_on_uninstall',
    'smartalloc_db_version',
    'smartalloc_metrics',
    'smartalloc_export_cb',
    'export_rate_limit',
    '_transient_smartalloc_metrics_cache',
];


<?php

declare(strict_types=1);

use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\DbSafe;

function smartalloc_run_migration_2025_08_28_add_override_fields_to_allocations_table(): void {
    global $wpdb;
    $resolver = new TableResolver($wpdb);
    $table    = $resolver->allocations(new FormContext(0));
    $sql      = DbSafe::mustPrepare(
        "ALTER TABLE {$table} ADD COLUMN overridden_by_user_id BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER allocated_at_utc, ADD COLUMN override_notes TEXT NULL DEFAULT NULL AFTER overridden_by_user_id",
        []
    );
    $wpdb->query($sql);
}

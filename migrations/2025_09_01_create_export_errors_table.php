<?php

declare(strict_types=1);

use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\DbSafe;

function smartalloc_run_migration_2025_09_01_create_export_errors_table(): void {
    global $wpdb;
    $resolver = new TableResolver($wpdb);
    $table    = $resolver->exportErrors();
    $sql      = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        allocation_id BIGINT UNSIGNED NOT NULL,
        error_type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_allocation (allocation_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $wpdb->query(DbSafe::mustPrepare($sql, [])); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
}

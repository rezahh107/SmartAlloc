<?php

declare(strict_types=1);

namespace SmartAlloc\Migrations;

use wpdb;

final class ExportLogMigrator
{
    public static function ensureTables(wpdb $db): void
    {
        $charset = method_exists($db, 'get_charset_collate') ? $db->get_charset_collate() : '';
        $log = $db->prefix . 'smartalloc_export_log';
        $err = $db->prefix . 'smartalloc_export_errors';
        $sqlLog = "CREATE TABLE IF NOT EXISTS `$log` (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_id BIGINT UNSIGNED NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            rows_ok INT NOT NULL DEFAULT 0,
            rows_error INT NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            error_text TEXT NULL
        ) $charset;";
        $sqlErr = "CREATE TABLE IF NOT EXISTS `$err` (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            export_id BIGINT UNSIGNED NOT NULL,
            form_id BIGINT UNSIGNED NOT NULL,
            row_index INT NOT NULL,
            column_name VARCHAR(100) NULL,
            message TEXT NOT NULL
        ) $charset;";
        $upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (is_readable($upgrade)) {
            require_once $upgrade;
        }
        if (function_exists('dbDelta')) {
            \dbDelta($sqlLog);
            \dbDelta($sqlErr);
        }
    }
}

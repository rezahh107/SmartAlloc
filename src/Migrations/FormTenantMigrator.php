<?php

declare(strict_types=1);

namespace SmartAlloc\Migrations;

final class FormTenantMigrator
{
    public static function ensureRegistryTable(\wpdb $db): void
    {
        $charset = $db->get_charset_collate();
        $t = $db->prefix . 'smartalloc_forms';
        $sql = "CREATE TABLE IF NOT EXISTS `$t` (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_id BIGINT UNSIGNED NOT NULL UNIQUE,
            status VARCHAR(10) NOT NULL DEFAULT 'disabled',
            schema_version VARCHAR(20) NOT NULL DEFAULT 'v1',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        \dbDelta($sql);
    }

    public static function provisionFormTenant(\wpdb $db, int $formId): void
    {
        $suffix = '_f' . $formId;
        $charset = $db->get_charset_collate();
        $tables = [
            "smartalloc_allocations{$suffix}" => "CREATE TABLE IF NOT EXISTS `{$db->prefix}smartalloc_allocations{$suffix}` (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                student_id BIGINT UNSIGNED NOT NULL,
                mentor_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(32) NOT NULL,
                meta JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) $charset;",
            "smartalloc_runs{$suffix}" => "CREATE TABLE IF NOT EXISTS `{$db->prefix}smartalloc_runs{$suffix}` (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                triggered_by BIGINT UNSIGNED NULL,
                mode VARCHAR(16) NOT NULL,
                summary JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) $charset;",
            "smartalloc_logs{$suffix}" => "CREATE TABLE IF NOT EXISTS `{$db->prefix}smartalloc_logs{$suffix}` (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(10) NOT NULL,
                message TEXT NOT NULL,
                context JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) $charset;",
            "smartalloc_dlq{$suffix}" => "CREATE TABLE IF NOT EXISTS `{$db->prefix}smartalloc_dlq{$suffix}` (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                payload JSON NOT NULL,
                reason VARCHAR(128) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) $charset;",
        ];
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $sql) {
            \dbDelta($sql);
        }
    }

    public static function dropFormTenant(\wpdb $db, int $formId): void
    {
        $suffix = '_f' . $formId;
        $tables = [
            "{$db->prefix}smartalloc_allocations{$suffix}",
            "{$db->prefix}smartalloc_runs{$suffix}",
            "{$db->prefix}smartalloc_logs{$suffix}",
            "{$db->prefix}smartalloc_dlq{$suffix}"
        ];
        foreach ($tables as $table) {
            // @security-ok-sql - table name is internal and suffixed with form ID
            $db->query("DROP TABLE IF EXISTS `$table`");
        }
    }
}

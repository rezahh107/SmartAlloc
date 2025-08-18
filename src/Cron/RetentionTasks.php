<?php

declare(strict_types=1);

namespace SmartAlloc\Cron;

use SmartAlloc\Infra\Settings\Settings;

final class RetentionTasks
{
    public static function register(): void
    {
        add_action('init', function (): void {
            if (!wp_next_scheduled('smartalloc_retention_daily')) {
                wp_schedule_event(time(), 'daily', 'smartalloc_retention_daily');
            }
        });
        add_action('smartalloc_retention_daily', [self::class, 'run']);
    }

    public static function run(): void
    {
        self::purgeExports(Settings::getExportRetentionDays());
        self::purgeLogs(Settings::getLogRetentionDays());
    }

    private static function purgeExports(int $days): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'smartalloc_exports';
        $upload = wp_upload_dir();
        $threshold = time() - ($days * 86400);
        $rows = $wpdb->get_results("SELECT id, path, created_at FROM {$table}", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $file = $row['path'];
            $created = strtotime($row['created_at'] ?? '') ?: 0;
            if (($days > 0 && $created < $threshold) || !file_exists($file)) {
                if (file_exists($file)) {
                    @unlink($file);
                }
                $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id=%d", (int) $row['id']));
            }
        }
    }

    private static function purgeLogs(int $days): void
    {
        if ($days <= 0) {
            return;
        }
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'smart-alloc/logs/';
        if (!is_dir($dir)) {
            return;
        }
        $threshold = time() - ($days * 86400);
        foreach (glob($dir . '*.log') as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && $mtime < $threshold) {
                @unlink($file);
            }
        }
    }
}

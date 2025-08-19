<?php

declare(strict_types=1);

namespace SmartAlloc\Cron;

/**
 * Handle export retention and integrity checks.
 */
final class ExportRetention
{
    public static function register(): void
    {
        add_action('init', function (): void {
            if (!wp_next_scheduled('smartalloc_export_retention')) {
                wp_schedule_event(time(), 'daily', 'smartalloc_export_retention');
            }
        });
        add_action('smartalloc_export_retention', [self::class, 'run']);
    }

    public static function run(): void
    {
        global $wpdb;
        $days = (int) get_option('export_retention_days', 30);
        $table = $wpdb->prefix . 'smartalloc_exports';
        $threshold = time() - ($days * DAY_IN_SECONDS);
        $rows = $wpdb->get_results("SELECT id,path,checksum,created_at FROM {$table}", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $id   = (int) ($row['id'] ?? 0);
            $path = (string) ($row['path'] ?? '');
            $created = strtotime($row['created_at'] ?? '') ?: 0;
            if ($days > 0 && $created < $threshold) {
                if (file_exists($path)) {
                    @unlink($path);
                }
                $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id=%d", $id));
                continue;
            }
            if (!file_exists($path)) {
                $wpdb->update($table, ['status' => 'Missing'], ['id' => $id]);
                continue;
            }
            $hash = hash_file('sha256', $path);
            $status = ($hash === ($row['checksum'] ?? '')) ? 'Valid' : 'Stale';
            $wpdb->update($table, ['status' => $status], ['id' => $id]);
        }
    }
}

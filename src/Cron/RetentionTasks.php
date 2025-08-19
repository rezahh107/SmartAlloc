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
        self::purgeLogs(Settings::getLogRetentionDays());
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

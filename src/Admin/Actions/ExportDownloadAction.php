<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Actions;

final class ExportDownloadAction
{
    public static function handle(): void
    {
        $id = isset($_GET['export_id']) ? absint($_GET['export_id']) : 0;

        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }

        if (!$id) {
            wp_die(esc_html__('Invalid export.', 'smartalloc'));
        }

        check_admin_referer('smartalloc_export_download_' . $id);

        global $wpdb;
        $table = $wpdb->prefix . 'smartalloc_exports';
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!$row) {
            wp_die(esc_html__('Export not found.', 'smartalloc'));
        }

        $upload = wp_upload_dir();
        $base   = realpath(trailingslashit($upload['basedir']) . 'smartalloc/exports');
        $path   = realpath($row['path']);
        if (!$path || !$base || !str_starts_with($path, $base)) {
            wp_die(esc_html__('Invalid file path.', 'smartalloc'));
        }

        if (!is_file($path) || !is_readable($path)) {
            wp_die(esc_html__('File not found.', 'smartalloc'));
        }

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($row['filename']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }
}

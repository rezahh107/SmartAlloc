<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Actions;

use SmartAlloc\Infra\Export\ExcelExporter;

final class ExportGenerateAction
{
    public static function handle(): void
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }

        check_admin_referer('smartalloc_export_generate', 'smartalloc_export_nonce');

        $mode    = sanitize_text_field($_POST['mode'] ?? '');
        $from    = sanitize_text_field($_POST['date_from'] ?? '');
        $to      = sanitize_text_field($_POST['date_to'] ?? '');
        $batchId = isset($_POST['batch_id']) ? absint($_POST['batch_id']) : 0;

        $filters = [];
        if ($mode === 'date-range') {
            if (!self::validDate($from) || !self::validDate($to)) {
                wp_die(esc_html__('Invalid date range.', 'smartalloc'));
            }
            $filters = ['mode' => 'date-range', 'from' => $from, 'to' => $to];
        } elseif ($mode === 'batch') {
            if ($batchId <= 0) {
                wp_die(esc_html__('Invalid batch ID.', 'smartalloc'));
            }
            $filters = ['mode' => 'batch', 'batch_id' => $batchId];
        } else {
            wp_die(esc_html__('Invalid mode.', 'smartalloc'));
        }

        $upload = wp_upload_dir();
        $dir    = trailingslashit($upload['basedir']) . 'smartalloc/exports/' . gmdate('Y/m/');
        wp_mkdir_p($dir);
        if (!is_writable($dir)) {
            wp_die(esc_html__('Export directory not writable.', 'smartalloc'));
        }

        global $wpdb;
        $exporter = new ExcelExporter($wpdb, null, $dir);
        if ($mode === 'date-range') {
            $result = $exporter->exportByDateRange($from . ' 00:00:00', $to . ' 23:59:59');
        } else {
            $result = $exporter->exportByBatchId($batchId);
        }

        $path     = $result['path'];
        $filename = basename($path);
        $size     = file_exists($path) ? filesize($path) : 0;
        $checksum = file_exists($path) ? hash_file('sha256', $path) : '';

        $table = $wpdb->prefix . 'smartalloc_exports';
        // @security-ok-sql
        $wpdb->insert($table, [
            'filename'   => $filename,
            'path'       => $path,
            'filters'    => wp_json_encode($filters),
            'size'       => $size,
            'checksum'   => $checksum ?: null,
            'created_at' => current_time('mysql'),
        ]);

        $url = add_query_arg(
            ['page' => 'smartalloc-export', 'smartalloc_export_success' => 1],
            admin_url('admin.php')
        );
        wp_safe_redirect($url);
    }

    private static function validDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        return wp_checkdate($m, $d, $y, $date);
    }
}

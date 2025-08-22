<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Pages;

use SmartAlloc\Domain\Export\CircuitBreaker;
use SmartAlloc\Infra\Metrics\MetricsCollector;

final class ExportPage
{
    public static function render(): void
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }

        $collector = new MetricsCollector();
        $breaker   = new CircuitBreaker($collector);

        $pluginFile = dirname(__DIR__, 3) . '/smart-alloc.php';
        wp_enqueue_script('smartalloc-export', plugins_url('assets/admin/export.js', $pluginFile), ['jquery'], SMARTALLOC_VERSION, true);
        wp_enqueue_style('smartalloc-export', plugins_url('assets/admin/export.css', $pluginFile), [], SMARTALLOC_VERSION);

        global $wpdb;
        $table   = $wpdb->prefix . 'smartalloc_exports';
        // @security-ok-sql
        $exports = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", 5),
            ARRAY_A
        );

        $logTable = $wpdb->prefix . 'salloc_export_log';
        $logs = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$logTable} ORDER BY created_at DESC LIMIT %d", 5),
            ARRAY_A
        );

        echo '<div class="smartalloc-admin"><div class="wrap">';
        echo '<h1>' . esc_html__('Export', 'smartalloc') . '</h1>';

        if (!$breaker->allow()) {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Exports temporarily disabled. Please retry later.', 'smartalloc') . '</p></div>';
        }

        if (isset($_GET['smartalloc_export_success'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Export generated.', 'smartalloc') . '</p></div>';
        }

        $snap = $collector->all();
        $total = $snap['counters']['exports_total'] ?? 0;
        $stale = $snap['counters']['stale_files'] ?? 0;
        echo '<div class="sa-export-metrics" aria-label="' . esc_attr(__( 'Export metrics', 'smartalloc' )) . '">';
        echo '<span>' . esc_html__('Exports total:', 'smartalloc') . ' ' . esc_html((string) $total) . '</span> ';
        echo '<span>' . esc_html__('Stale files:', 'smartalloc') . ' ' . esc_html((string) $stale) . '</span>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="smartalloc_export_generate" />';
        wp_nonce_field('smartalloc_export_generate', 'smartalloc_export_nonce');

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="sa_mode">' . esc_html__('Mode', 'smartalloc') . '</label></th><td><select id="sa_mode" name="mode">';
        echo '<option value="date-range">' . esc_html__('Date range', 'smartalloc') . '</option>';
        echo '<option value="batch">' . esc_html__('Batch', 'smartalloc') . '</option>';
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="sa_date_from">' . esc_html__('Date from', 'smartalloc') . '</label></th><td><input type="date" id="sa_date_from" name="date_from" /></td></tr>';
        echo '<tr><th scope="row"><label for="sa_date_to">' . esc_html__('Date to', 'smartalloc') . '</label></th><td><input type="date" id="sa_date_to" name="date_to" /></td></tr>';
        echo '<tr><th scope="row"><label for="sa_batch_id">' . esc_html__('Batch ID', 'smartalloc') . '</label></th><td><input type="number" id="sa_batch_id" name="batch_id" min="1" /></td></tr>';
        echo '</tbody></table>';

        submit_button(esc_html__('Generate export', 'smartalloc'));
        echo '</form>';

        echo '<h2>' . esc_html__('Recent exports', 'smartalloc') . '</h2>';
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Filename', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Created', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Filters', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Size', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Checksum', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Status', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Action', 'smartalloc') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($exports as $export) {
            $filters  = json_decode($export['filters'] ?? '', true) ?: [];
            $summary  = self::summary($filters);
            $download = wp_nonce_url(
                admin_url('admin-post.php?action=smartalloc_export_download&export_id=' . absint($export['id'])),
                'smartalloc_export_download_' . absint($export['id'])
            );
            echo '<tr>';
            echo '<td>' . esc_html($export['filename']) . '</td>';
            echo '<td>' . esc_html($export['created_at']) . '</td>';
            echo '<td>' . esc_html($summary) . '</td>';
            echo '<td>' . esc_html(size_format((int) $export['size'])) . '</td>';
            echo '<td>' . esc_html($export['checksum'] ?? '') . '</td>';
            $status = strtolower((string) ($export['status'] ?? ''));
            $label  = $status ?: 'missing';
            echo '<td><span class="sa-status sa-status-' . esc_attr($status) . '" aria-label="' . esc_attr($label) . '">' . esc_html(ucfirst($label)) . '</span></td>';
            echo '<td><a href="' . esc_url($download) . '">' . esc_html__('Download', 'smartalloc') . '</a></td>';
            echo '</tr>';
        }

        if (empty($exports)) {
        echo '<tr><td colspan="7">' . esc_html__('No exports found.', 'smartalloc') . '</td></tr>';
        }

        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Logs', 'smartalloc') . '</h2>';
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Filename', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Rows', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Failures', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Duration (ms)', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Created', 'smartalloc') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log['file_name']) . '</td>';
            echo '<td>' . esc_html((string) ($log['rows_total'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($log['rows_failed'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($log['duration_ms'] ?? 0)) . '</td>';
            echo '<td>' . esc_html($log['created_at'] ?? '') . '</td>';
            echo '</tr>';
        }
        if (empty($logs)) {
            echo '<tr><td colspan="5">' . esc_html__('No logs found.', 'smartalloc') . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div></div>';
    }

    private static function summary(array $filters): string
    {
        if (($filters['mode'] ?? '') === 'date-range') {
            return ($filters['from'] ?? '') . ' - ' . ($filters['to'] ?? '');
        }
        if (($filters['mode'] ?? '') === 'batch') {
            return 'Batch ' . ($filters['batch_id'] ?? '');
        }
        return '';
    }
}

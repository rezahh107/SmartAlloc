<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Pages;

final class ExportPage
{
    public static function render(): void
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'smartalloc_exports';
        $exports = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", 20),
            ARRAY_A
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Export', 'smartalloc') . '</h1>';

        if (isset($_GET['smartalloc_export_success'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Export generated.', 'smartalloc') . '</p></div>';
        }

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
            echo '<td><a href="' . esc_url($download) . '">' . esc_html__('Download', 'smartalloc') . '</a></td>';
            echo '</tr>';
        }

        if (empty($exports)) {
            echo '<tr><td colspan="6">' . esc_html__('No exports found.', 'smartalloc') . '</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
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

<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Pages;

use SmartAlloc\Http\Rest\ReportsMetricsController;

/**
 * Admin reports page.
 */
final class ReportsPage
{
    /**
     * Render the reports page.
     */
    public static function render(): void
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }

        $filters = array(
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to'   => sanitize_text_field($_GET['date_to'] ?? ''),
            'center'    => sanitize_text_field($_GET['center'] ?? ''),
            'group'     => sanitize_text_field($_GET['group'] ?? ''),
            'gender'    => sanitize_text_field($_GET['gender'] ?? ''),
            'group_by'  => sanitize_text_field($_GET['group_by'] ?? 'day'),
        );

        $metrics = apply_filters('smartalloc_reports_metrics', null, $filters);
        if ($metrics === null) {
            $metrics = ReportsMetricsController::query($filters);
        }

        $csv_url = wp_nonce_url(
            admin_url('admin-post.php?action=smartalloc_reports_csv'),
            'smartalloc_reports_csv',
            'smartalloc_reports_nonce'
        );

        echo '<div class="smartalloc-admin"><div class="wrap">';
        echo '<h1>' . esc_html__('Reports', 'smartalloc') . '</h1>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="smartalloc-reports" />';
        echo '<p>';
        echo '<label>' . esc_html__('From', 'smartalloc') . ' <input type="date" name="date_from" value="' . esc_attr($filters['date_from']) . '" /></label> ';
        echo '<label>' . esc_html__('To', 'smartalloc') . ' <input type="date" name="date_to" value="' . esc_attr($filters['date_to']) . '" /></label> ';
        echo '<label>' . esc_html__('Center', 'smartalloc') . ' <input type="text" name="center" value="' . esc_attr($filters['center']) . '" /></label> ';
        echo '<label>' . esc_html__('Group', 'smartalloc') . ' <input type="text" name="group" value="' . esc_attr($filters['group']) . '" /></label> ';
        echo '<label>' . esc_html__('Gender', 'smartalloc') . ' <input type="text" name="gender" value="' . esc_attr($filters['gender']) . '" /></label> ';
        echo '<label>' . esc_html__('Group by', 'smartalloc') . ' <select name="group_by">';
        foreach (array('day', 'center', 'mentor') as $opt) {
            $sel = $filters['group_by'] === $opt ? ' selected' : '';
            echo '<option value="' . esc_attr($opt) . '"' . $sel . '>' . esc_html($opt) . '</option>';
        }
        echo '</select></label>';
        submit_button(esc_html__('Filter', 'smartalloc'), 'primary', '', false);
        echo '</p>';
        echo '</form>';

        $tot = $metrics['total'] ?? array('allocated' => 0, 'manual' => 0, 'reject' => 0, 'fuzzy_auto_rate' => 0, 'fuzzy_manual_rate' => 0, 'capacity_used' => 0);
        echo '<h2>' . esc_html__('Totals', 'smartalloc') . '</h2>';
        echo '<ul class="kpis">';
        echo '<li>' . esc_html__('Allocated', 'smartalloc') . ': ' . esc_html((string) $tot['allocated']) . '</li>';
        echo '<li>' . esc_html__('Manual', 'smartalloc') . ': ' . esc_html((string) $tot['manual']) . '</li>';
        echo '<li>' . esc_html__('Reject', 'smartalloc') . ': ' . esc_html((string) $tot['reject']) . '</li>';
        echo '</ul>';

        echo '<h2>' . esc_html__('Details', 'smartalloc') . '</h2>';
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr>'; 
        echo '<th>' . esc_html__('Key', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Allocated', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Manual', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Reject', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Fuzzy Auto', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Fuzzy Manual', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Capacity Used', 'smartalloc') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($metrics['rows'] as $row) {
            $key = $row['date'] ?? ($row['center'] ?? '');
            echo '<tr>';
            echo '<td>' . esc_html((string) $key) . '</td>';
            echo '<td>' . esc_html((string) $row['allocated']) . '</td>';
            echo '<td>' . esc_html((string) $row['manual']) . '</td>';
            echo '<td>' . esc_html((string) $row['reject']) . '</td>';
            echo '<td>' . esc_html(number_format((float) $row['fuzzy_auto_rate'], 2)) . '</td>';
            echo '<td>' . esc_html(number_format((float) $row['fuzzy_manual_rate'], 2)) . '</td>';
            echo '<td>' . esc_html(number_format((float) $row['capacity_used'], 2)) . '</td>';
            echo '</tr>';
        }

        if (empty($metrics['rows'])) {
            echo '<tr><td colspan="7">' . esc_html__('No data', 'smartalloc') . '</td></tr>';
        }

        echo '</tbody></table>';

        echo '<p><a href="' . esc_url($csv_url) . '">' . esc_html__('Export CSV', 'smartalloc') . '</a></p>';
        echo '</div></div>';
    }

    /**
     * Download CSV export for current filters.
     */
    public static function downloadCsv(): void
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }

        check_admin_referer('smartalloc_reports_csv', 'smartalloc_reports_nonce');

        $filters = array(
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to'   => sanitize_text_field($_GET['date_to'] ?? ''),
            'center'    => sanitize_text_field($_GET['center'] ?? ''),
            'group'     => sanitize_text_field($_GET['group'] ?? ''),
            'gender'    => sanitize_text_field($_GET['gender'] ?? ''),
            'group_by'  => sanitize_text_field($_GET['group_by'] ?? 'day'),
        );
        $metrics = apply_filters('smartalloc_reports_metrics', null, $filters);
        if ($metrics === null) {
            $metrics = ReportsMetricsController::query($filters);
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="smartalloc-report.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, array('key', 'allocated', 'manual', 'reject', 'fuzzy_auto_rate', 'fuzzy_manual_rate', 'capacity_used'));
        foreach ($metrics['rows'] as $row) {
            $key = $row['date'] ?? ($row['center'] ?? '');
            $values = array(
                $key,
                $row['allocated'],
                $row['manual'],
                $row['reject'],
                $row['fuzzy_auto_rate'],
                $row['fuzzy_manual_rate'],
                $row['capacity_used'],
            );
            $sanitized = array_map(['\SmartAlloc\Infra\Export\FormulaEscaper', 'escape'], array_map('strval', $values));
            fputcsv($out, $sanitized);
        }
        fclose($out);
    }
}

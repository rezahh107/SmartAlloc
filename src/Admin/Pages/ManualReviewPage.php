<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Pages;

use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Infra\Logger\NullLogger;

final class ManualReviewPage
{
    public static function render(): void
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }

        $pluginFile = dirname(__DIR__, 3) . '/smart-alloc.php';
        wp_enqueue_script('smartalloc-manual-review', plugins_url('assets/admin/manual-review.js', $pluginFile), ['jquery', 'wp-api-fetch'], SMARTALLOC_VERSION, true);
        wp_enqueue_style('smartalloc-manual-review', plugins_url('assets/admin/manual-review.css', $pluginFile), [], SMARTALLOC_VERSION);

        $page    = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $filters = [
            'reason_code' => isset($_GET['reason_code']) ? sanitize_text_field((string) $_GET['reason_code']) : null,
            'date_from'   => isset($_GET['date_from']) ? sanitize_text_field((string) $_GET['date_from']) : null,
            'date_to'     => isset($_GET['date_to']) ? sanitize_text_field((string) $_GET['date_to']) : null,
        ];

        $repo = apply_filters('smartalloc_allocations_repository', null);
        if (!$repo instanceof AllocationsRepository) {
            global $smartalloc_repo;
            if (is_object($smartalloc_repo)) {
                $repo = $smartalloc_repo;
            } else {
                global $wpdb;
                $repo = new AllocationsRepository(new NullLogger(), $wpdb);
            }
        }

        $data = $repo->findManualPage($page, 20, array_filter($filters));

        echo '<div class="smartalloc-admin"><div class="wrap">';
        echo '<h1>' . esc_html__('Manual Review', 'smartalloc') . '</h1>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="smartalloc-manual-review" />';
        echo '<input type="text" name="reason_code" placeholder="reason" value="' . esc_attr((string)($filters['reason_code'] ?? '')) . '" /> ';
        echo '<input type="date" name="date_from" value="' . esc_attr((string)($filters['date_from'] ?? '')) . '" /> ';
        echo '<input type="date" name="date_to" value="' . esc_attr((string)($filters['date_to'] ?? '')) . '" /> ';
        submit_button(__('Filter'), '', '', false);
        echo '</form>';

        echo '<form method="post" id="smartalloc-manual-form">';
        wp_nonce_field('smartalloc_manual_action', 'nonce');
        echo '<table class="widefat"><thead><tr>';
        echo '<th class="check-column"><input type="checkbox" id="cb-select-all" /></th>';
        echo '<th>' . esc_html__('Entry ID', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Status', 'smartalloc') . '</th>';
        echo '<th>' . esc_html__('Actions', 'smartalloc') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($data['rows'] as $row) {
            $id = (int) $row['entry_id'];
            $candidates = [];
            if (!empty($row['candidates'])) {
                $candidates = json_decode((string) $row['candidates'], true) ?: [];
            }
            $first = $candidates[0] ?? [];
            $mentor = (int) ($first['mentor_id'] ?? 0);
            $used = (int) ($first['used'] ?? 0);
            $capacity = (int) ($first['capacity'] ?? 0);
            $full = $capacity > 0 && $used >= $capacity;
            echo '<tr>';
            echo '<th scope="row" class="check-column"><input type="checkbox" name="entry_ids[]" value="' . esc_attr((string)$id) . '" /></th>';
            echo '<td>' . esc_html((string)$id) . '</td>';
            echo '<td>' . esc_html((string)$row['status']);
            if ($capacity > 0) {
                echo ' <span class="smartalloc-capacity">(' . esc_html((string)$used) . '/' . esc_html((string)$capacity) . ')</span>';
            }
            echo '</td>';
            echo '<td>';
            $approveAttrs = 'class="button smartalloc-approve" aria-label="Approve entry ' . esc_attr((string)$id) . '" data-entry="' . esc_attr((string)$id) . '" data-mentor="' . esc_attr((string)$mentor) . '"';
            if ($full) { $approveAttrs .= ' disabled data-full="1"'; }
            echo '<button ' . $approveAttrs . '>' . esc_html__('Approve', 'smartalloc') . '</button> ';
            echo '<button class="button smartalloc-reject" aria-label="Reject entry ' . esc_attr((string)$id) . '" data-entry="' . esc_attr((string)$id) . '">' . esc_html__('Reject', 'smartalloc') . '</button> ';
            echo '<button class="button smartalloc-defer" aria-label="Defer entry ' . esc_attr((string)$id) . '" data-entry="' . esc_attr((string)$id) . '">' . esc_html__('Defer', 'smartalloc') . '</button>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p>';
        echo '<button class="button" id="smartalloc-bulk-approve" aria-label="Approve selected entries">' . esc_html__('Approve Selected', 'smartalloc') . '</button> ';
        echo '<button class="button" id="smartalloc-bulk-reject" aria-label="Reject selected entries">' . esc_html__('Reject Selected', 'smartalloc') . '</button> ';
        echo '<button class="button" id="smartalloc-bulk-defer" aria-label="Defer selected entries">' . esc_html__('Defer Selected', 'smartalloc') . '</button>';
        echo '</p>';
        echo '</form>';
        echo '</div></div>';
    }
}



<?php
// @security-ok-rest

declare(strict_types=1);
/**
 * Register SmartAlloc metrics endpoints.
 */
function sa_register_metrics_endpoints(): void
{
    add_action('rest_api_init', function (): void {
        register_rest_route('smartalloc/v1', '/metrics', [
            'methods'             => 'GET',
            'permission_callback' => fn() => current_user_can(SMARTALLOC_CAP),
            'callback'            => 'sa_metrics_handle',
        ]);
    });
}

/**
 * Handle metrics endpoint.
 *
 * @return WP_REST_Response|WP_Error
 */
function sa_metrics_handle(WP_REST_Request $request)
{
    if (!current_user_can(SMARTALLOC_CAP)) {
        return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
    }

    global $wpdb;
    $allocTable   = $wpdb->prefix . 'smartalloc_allocations';
    $mentorsTable = $wpdb->prefix . 'salloc_mentors';
    $exportsTable = $wpdb->prefix . 'salloc_exports';
    $validTable   = $wpdb->prefix . 'salloc_validation_errors';
    $dlqTable     = $wpdb->prefix . 'salloc_dlq';

    // Allocation counts
    $totalAlloc = (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$allocTable}") ?: 0);
    $byMentor   = $wpdb->get_results("SELECT mentor_id, assigned, capacity FROM {$mentorsTable} ORDER BY mentor_id", ARRAY_A) ?: [];
    foreach ($byMentor as &$m) {
        $m['mentor_id'] = (int) $m['mentor_id'];
        $m['assigned']  = (int) $m['assigned'];
        $m['capacity']  = (int) min($m['capacity'], 60);
    }
    unset($m);
    usort($byMentor, fn($a, $b) => $a['mentor_id'] <=> $b['mentor_id']);
    $byCenter = $wpdb->get_results("SELECT center_id, SUM(assigned) AS assigned, SUM(capacity) AS capacity FROM {$mentorsTable} GROUP BY center_id ORDER BY center_id", ARRAY_A) ?: [];
    foreach ($byCenter as &$c) {
        $c['center_id'] = (int) $c['center_id'];
        $c['assigned']  = (int) $c['assigned'];
        $c['capacity']  = (int) min($c['capacity'], 60);
    }
    unset($c);
    usort($byCenter, fn($a, $b) => $a['center_id'] <=> $b['center_id']);

    // Export counts
    $exports = [
        'total'  => (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$exportsTable}") ?: 0),
        'errors' => (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$exportsTable} WHERE status='error'") ?: 0),
    ];

    // Validation errors grouped by rule and field
    $validation = $wpdb->get_results("SELECT rule, field, COUNT(*) AS count FROM {$validTable} GROUP BY rule, field ORDER BY rule, field", ARRAY_A) ?: [];
    foreach ($validation as &$v) {
        $v['rule']  = (string) $v['rule'];
        $v['field'] = (int) $v['field'];
        $v['count'] = (int) $v['count'];
    }
    unset($v);
    usort($validation, fn($a, $b) => [$a['rule'], $a['field']] <=> [$b['rule'], $b['field']]);

    // DLQ backlog stats
    $dlq = [
        'total' => (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$dlqTable}") ?: 0),
    ]; // @security-ok-sql

    $metrics = [
        'allocations'       => [
            'total'     => $totalAlloc,
            'by_mentor' => $byMentor,
            'by_center' => $byCenter,
        ],
        'exports'           => $exports,
        'validation_errors' => $validation,
        'dlq'               => $dlq,
        'timestamp_utc'     => gmdate('Y-m-d\TH:i:s\Z'),
    ];
    ksort($metrics);

    return new WP_REST_Response($metrics, 200);
}

if (PHP_SAPI !== 'cli') {
    sa_register_metrics_endpoints();
}

<?php

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Metrics aggregation endpoint used by the admin reports page.
 *
 * This legacy controller exposes aggregated allocation metrics and
 * remains in place for backwards compatibility. The production
 * observability endpoint for export metrics lives in
 * {@see \SmartAlloc\Http\Rest\MetricsController}.
 */
final class ReportsMetricsController
{
    /**
     * Register REST routes.
     */
    public function register_routes(): void
    {
        add_action(
            'rest_api_init',
            function (): void {
                register_rest_route(
                    'smartalloc/v1',
                    '/report-metrics',
                    array(
                        'methods'             => 'GET',
                        'permission_callback' => function (): bool {
                            return current_user_can(SMARTALLOC_CAP);
                        },
                        'callback'            => array($this, 'handle'),
                    )
                );
            }
        );
    }

    /**
     * Handle metrics request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function handle(WP_REST_Request $request)
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            return new WP_Error('forbidden', 'Forbidden', array('status' => 403));
        }

        $params = method_exists($request, 'get_params') ? (array) $request->get_params() : array();
        if (empty($params)) {
            $params = $_GET;
        }

        $from = $params['date_from'] ?? '';
        $to   = $params['date_to'] ?? '';
        if ($from && $to) {
            $diff = strtotime((string)$to) - strtotime((string)$from);
            if ($diff > 90 * 86400) {
                return new WP_Error('range_too_large', 'Date range too large', array('status' => 400));
            }
        }

        $ttl = \SmartAlloc\Infra\Settings\Settings::getMetricsCacheTtl();
        $key = 'smartalloc_metrics_' . md5(wp_json_encode($params));
        if ($ttl > 0) {
            $cached = get_transient($key);
            if ($cached !== false) {
                return new WP_REST_Response($cached, 200);
            }
        }

        $data = self::query($params);
        if ($ttl > 0) {
            set_transient($key, $data, $ttl);
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Build metrics query and aggregate results.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function query(array $params): array
    {
        global $wpdb;
        $alloc_table  = $wpdb->prefix . 'smartalloc_allocations';
        $mentors_table = $wpdb->prefix . 'salloc_mentors';

        $date_from = sanitize_text_field($params['date_from'] ?? '');
        $date_to   = sanitize_text_field($params['date_to'] ?? '');
        $group_by  = sanitize_text_field($params['group_by'] ?? 'day');

        $where  = array();
        $values = array();
        if ($date_from !== '') {
            $where[]  = 'a.created_at >= %s';
            $values[] = $date_from . ' 00:00:00';
        }
        if ($date_to !== '') {
            $where[]  = 'a.created_at <= %s';
            $values[] = $date_to . ' 23:59:59';
        }
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        if ($group_by === 'center') {
            $sql = "SELECT m.center_id AS grp,
                        SUM(CASE WHEN a.status = 'auto' THEN 1 ELSE 0 END) AS auto_count,
                        SUM(CASE WHEN a.status = 'manual' THEN 1 ELSE 0 END) AS manual_count,
                        SUM(CASE WHEN a.status = 'reject' THEN 1 ELSE 0 END) AS reject_count,
                        SUM(0) AS fuzzy_auto,
                        SUM(0) AS fuzzy_manual,
                        SUM(0) AS assigned,
                        SUM(0) AS capacity
                    FROM {$alloc_table} a
                    LEFT JOIN {$mentors_table} m ON a.mentor_id = m.mentor_id
                    {$where_sql}
                    GROUP BY m.center_id";
        } else {
            $sql = "SELECT DATE(a.created_at) AS grp,
                        SUM(CASE WHEN a.status = 'auto' THEN 1 ELSE 0 END) AS auto_count,
                        SUM(CASE WHEN a.status = 'manual' THEN 1 ELSE 0 END) AS manual_count,
                        SUM(CASE WHEN a.status = 'reject' THEN 1 ELSE 0 END) AS reject_count,
                        SUM(0) AS fuzzy_auto,
                        SUM(0) AS fuzzy_manual,
                        SUM(0) AS assigned,
                        SUM(0) AS capacity
                    FROM {$alloc_table} a
                    {$where_sql}
                    GROUP BY DATE(a.created_at)
                    ORDER BY DATE(a.created_at) ASC
                    LIMIT 60";
        }

        $prepared = $values ? $wpdb->prepare($sql, $values) : $sql;
        $rows     = $wpdb->get_results($prepared, ARRAY_A) ?: array();

        $result_rows = array();
        $totals = array(
            'allocated'         => 0,
            'manual'            => 0,
            'reject'            => 0,
            'fuzzy_auto'        => 0,
            'fuzzy_manual'      => 0,
            'assigned'          => 0,
            'capacity'          => 0,
        );

          foreach ($rows as $row) {
              if (!isset($row['grp'])) {
                  continue;
              }
              $allocated = (int) ($row['auto_count'] ?? 0);
              $manual    = (int) ($row['manual_count'] ?? 0);
              $reject    = (int) ($row['reject_count'] ?? 0);
              $fuzzy_auto   = (float) ($row['fuzzy_auto'] ?? 0);
              $fuzzy_manual = (float) ($row['fuzzy_manual'] ?? 0);
              $assigned = (float) ($row['assigned'] ?? 0);
              $capacity = (float) ($row['capacity'] ?? 0);

              $result_rows[] = array(
                  $group_by === 'center' ? 'center' : 'date' => $row['grp'],
                  'allocated'         => $allocated,
                  'manual'            => $manual,
                  'reject'            => $reject,
                  'fuzzy_auto_rate'   => $allocated > 0 ? $fuzzy_auto / $allocated : 0.0,
                  'fuzzy_manual_rate' => $manual > 0 ? $fuzzy_manual / $manual : 0.0,
                  'capacity_used'     => $capacity > 0 ? $assigned / $capacity : 0.0,
              );

              $totals['allocated']    += $allocated;
              $totals['manual']       += $manual;
              $totals['reject']       += $reject;
              $totals['fuzzy_auto']   += $fuzzy_auto;
              $totals['fuzzy_manual'] += $fuzzy_manual;
              $totals['assigned']     += $assigned;
              $totals['capacity']     += $capacity;
          }

        $total = array(
            'allocated'         => $totals['allocated'],
            'manual'            => $totals['manual'],
            'reject'            => $totals['reject'],
            'fuzzy_auto_rate'   => $totals['allocated'] > 0 ? $totals['fuzzy_auto'] / $totals['allocated'] : 0.0,
            'fuzzy_manual_rate' => $totals['manual'] > 0 ? $totals['fuzzy_manual'] / $totals['manual'] : 0.0,
            'capacity_used'     => $totals['capacity'] > 0 ? $totals['assigned'] / $totals['capacity'] : 0.0,
        );

        return array('rows' => $result_rows, 'total' => $total);
    }
}

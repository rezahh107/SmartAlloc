<?php
// @security-ok-rest

declare(strict_types=1);

namespace SmartAlloc\Http;

use SmartAlloc\Container;
use SmartAlloc\Services\{HealthService, ExportService, Metrics};
use SmartAlloc\Infra\Repository\AllocationsRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller
 */
final class RestController
{
    private array $reasonAllowlist = ['duplicate', 'invalid'];

    public function __construct(private Container $container) {}

    /**
     * Register REST routes
     */
    public function register_routes(): void
    {
        add_action('rest_api_init', function() {
            // Health endpoint
            register_rest_route('smartalloc/v1', '/health', [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => function() {
                    if (!current_user_can('read')) {
                        return new \WP_Error('forbidden', 'Forbidden', ['status' => 403]);
                    }
                    return $this->container->get(HealthService::class)->status();
                }
            ]);

            // Metrics endpoint
            register_rest_route('smartalloc/v1', '/metrics', [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => function() {
                    if (!current_user_can(SMARTALLOC_CAP)) {
                        return new \WP_Error('forbidden', 'Forbidden', ['status' => 403]);
                    }
                    return $this->getMetrics();
                }
            ]);

            // Export endpoint
            register_rest_route('smartalloc/v1', '/export', [
                'methods' => 'POST',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    return current_user_can(SMARTALLOC_CAP) &&
                        wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest');
                },
                'callback' => function($request) {
                    return $this->export($request);
                },
                'args' => [
                    'rows' => [
                        'required' => true,
                        'type' => 'array',
                        'validate_callback' => function($rows) {
                            return is_array($rows) && !empty($rows);
                        }
                    ]
                ]
            ]);

            // Manual review approve endpoint
            register_rest_route('smartalloc/v1', '/review/(?P<entry>\d+)/approve', [
                'methods' => 'POST',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    return current_user_can(SMARTALLOC_CAP) &&
                        wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest');
                },
                'callback' => function(WP_REST_Request $request) {
                    return $this->approveManual($request);
                },
            ]);

            // Manual review reject endpoint
            register_rest_route('smartalloc/v1', '/review/(?P<entry>\d+)/reject', [
                'methods' => 'POST',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    return current_user_can(SMARTALLOC_CAP) &&
                        wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest');
                },
                'callback' => function(WP_REST_Request $request) {
                    return $this->rejectManual($request);
                },
            ]);

            // Manual review defer endpoint
            register_rest_route('smartalloc/v1', '/review/(?P<entry>\d+)/defer', [
                'methods' => 'POST',
                'permission_callback' => function (WP_REST_Request $request): bool {
                    return current_user_can(SMARTALLOC_CAP) &&
                        wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest');
                },
                'callback' => function(WP_REST_Request $request) {
                    return $this->deferManual($request);
                },
            ]);
        });
    }

    /**
     * Get metrics data
     */
    private function getMetrics(): array
    {
        $key = sanitize_text_field($_GET['key'] ?? '');
        $limit = max(1, min(1000, (int) ($_GET['limit'] ?? 100)));
        
        $metrics = $this->container->get(Metrics::class);
        
        if ($key) {
            return $metrics->get($key, $limit);
        }
        
        // Return aggregated metrics
        return [
            'export_success_total' => $this->getAggregatedMetric('export_success_total'),
            'export_failed_total' => $this->getAggregatedMetric('export_failed_total'),
            'export_duration_ms_sum' => $this->getAggregatedMetric('export_duration_ms_sum'),
            'allocations_committed_total' => $this->getAggregatedMetric('allocations_committed_total')
        ];
    }

    /**
     * Export data
     */
    private function export($request): array
    {
        // Validate nonce for additional security
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return [
                'success' => false,
                'error' => 'Invalid nonce or missing authentication'
            ];
        }

        $rows = $request->get_param('rows') ?: [];
        
        if (empty($rows)) {
            return [
                'success' => false,
                'error' => 'No data provided for export'
            ];
        }

        // Validate rows structure
        if (!$this->validateRowsStructure($rows)) {
            return [
                'success' => false,
                'error' => 'Invalid rows structure provided'
            ];
        }
        
        try {
            $exportService = $this->container->get(ExportService::class);
            $filePath = $exportService->exportSabt($rows);
            
            return [
                'success' => true,
                'file' => $filePath,
                'rows_exported' => count($rows)
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate rows structure for export
     */
    private function validateRowsStructure(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                return false;
            }
            
            // Check for required fields if needed
            // This can be customized based on your export requirements
        }
        
        return true;
    }

    /**
     * Get aggregated metric
     */
    private function getAggregatedMetric(string $key): float
    {
        $metrics = $this->container->get(Metrics::class);
        $data = $metrics->getAggregated($key, 'sum', 24);

        return (float) ($data[0]['value'] ?? 0);
    }

    /**
     * Handle manual approval via REST.
     */
    private function approveManual(WP_REST_Request $request): WP_REST_Response
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'invalid_nonce',
                'message' => 'Invalid nonce'
            ], 403);
        }

        $entryId  = absint($request->get_param('entry'));
        $mentorId = absint($request->get_param('mentor_id'));
        if ($entryId <= 0 || $mentorId <= 0) {
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'invalid_params',
                'message' => 'Invalid parameters'
            ], 400);
        }

        $lockKey   = 'smartalloc_review_lock_' . $entryId;
        $lockOwner = get_transient($lockKey);
        $current   = (string) get_current_user_id();
        if ($lockOwner && $lockOwner !== $current) {
            $this->container->get(Metrics::class)->inc('review_lock_hit');
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'entry_locked',
                'message' => 'Entry locked'
            ], 409);
        }
        set_transient($lockKey, $current, 5 * MINUTE_IN_SECONDS);

        $repo   = $this->container->get(AllocationsRepository::class);
        $result = $repo->approveManual($entryId, $mentorId, (int) $current, null);

        delete_transient($lockKey);

        $metrics = $this->container->get(Metrics::class);
        $data = $result->to_array();
        if (($data['committed'] ?? false) !== true) {
            if (($data['reason'] ?? '') === 'capacity') {
                $metrics->inc('review_capacity_blocked');
                delete_transient('smartalloc_metrics_cache');
                return new WP_REST_Response([
                    'ok' => false,
                    'code' => 'capacity_exceeded',
                    'message' => 'Mentor capacity exceeded'
                ], 409);
            }
            $metrics->inc('review_duplicate_blocked');
            delete_transient('smartalloc_metrics_cache');
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'duplicate_allocation',
                'message' => 'Allocation already processed'
            ], 409);
        }
        $metrics->inc('review_approve_total');
        delete_transient('smartalloc_metrics_cache');
        return new WP_REST_Response([
            'ok' => true,
            'code' => 'approved',
            'message' => 'Approved',
            'result' => $data
        ]);
    }

    /**
     * Handle manual rejection via REST.
     */
    private function rejectManual(WP_REST_Request $request): WP_REST_Response
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'invalid_nonce',
                'message' => 'Invalid nonce'
            ], 403);
        }

        $entryId = absint($request->get_param('entry'));
        $reason  = sanitize_key((string) $request->get_param('reason'));
        if ($entryId <= 0 || $reason === '' || !in_array($reason, $this->reasonAllowlist, true)) {
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'invalid_params',
                'message' => 'Invalid parameters'
            ], 400);
        }

        $lockKey   = 'smartalloc_review_lock_' . $entryId;
        $lockOwner = get_transient($lockKey);
        $current   = (string) get_current_user_id();
        if ($lockOwner && $lockOwner !== $current) {
            $this->container->get(Metrics::class)->inc('review_lock_hit');
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'entry_locked',
                'message' => 'Entry locked'
            ], 409);
        }
        set_transient($lockKey, $current, 5 * MINUTE_IN_SECONDS);

        $repo = $this->container->get(AllocationsRepository::class);
        $repo->rejectManual($entryId, (int) $current, $reason, null);

        delete_transient($lockKey);

        $metrics = $this->container->get(Metrics::class);
        $metrics->inc('review_reject_total');
        delete_transient('smartalloc_metrics_cache');

        return new WP_REST_Response([
            'ok' => true,
            'code' => 'rejected',
            'message' => 'Rejected'
        ]);
    }

    /**
     * Handle manual defer via REST.
     */
    private function deferManual(WP_REST_Request $request): WP_REST_Response
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'invalid_nonce',
                'message' => 'Invalid nonce'
            ], 403);
        }

        $entryId = absint($request->get_param('entry'));
        $note    = sanitize_text_field((string) $request->get_param('note'));
        if ($entryId <= 0) {
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'invalid_params',
                'message' => 'Invalid parameters'
            ], 400);
        }

        $lockKey   = 'smartalloc_review_lock_' . $entryId;
        $lockOwner = get_transient($lockKey);
        $current   = (string) get_current_user_id();
        if ($lockOwner && $lockOwner !== $current) {
            $this->container->get(Metrics::class)->inc('review_lock_hit');
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'entry_locked',
                'message' => 'Entry locked'
            ], 409);
        }
        set_transient($lockKey, $current, 5 * MINUTE_IN_SECONDS);

        $repo = $this->container->get(AllocationsRepository::class);
        $ok   = $repo->deferManual($entryId, (int) $current, $note);

        delete_transient($lockKey);

        if (!$ok) {
            $metrics = $this->container->get(Metrics::class);
            $metrics->inc('review_duplicate_blocked');
            delete_transient('smartalloc_metrics_cache');
            return new WP_REST_Response([
                'ok' => false,
                'code' => 'duplicate_allocation',
                'message' => 'Allocation already processed'
            ], 409);
        }

        $metrics = $this->container->get(Metrics::class);
        $metrics->inc('review_defer_total');
        delete_transient('smartalloc_metrics_cache');

        return new WP_REST_Response([
            'ok' => true,
            'code' => 'deferred',
            'message' => 'Deferred'
        ]);
    }
}


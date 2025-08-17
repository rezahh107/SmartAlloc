<?php

declare(strict_types=1);

namespace SmartAlloc\Http;

use SmartAlloc\Container;
use SmartAlloc\Services\{HealthService, ExportService, Metrics};

/**
 * REST API controller
 */
final class RestController
{
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
                    return $this->container->get(HealthService::class)->status();
                }
            ]);

            // Metrics endpoint
            register_rest_route('smartalloc/v1', '/metrics', [
                'methods' => 'GET',
                'permission_callback' => function() {
                    return current_user_can(SMARTALLOC_CAP);
                },
                'callback' => function() {
                    return $this->getMetrics();
                }
            ]);

            // Export endpoint
            register_rest_route('smartalloc/v1', '/export', [
                'methods' => 'POST',
                'permission_callback' => function() {
                    return current_user_can(SMARTALLOC_CAP);
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
} 
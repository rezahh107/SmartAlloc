<?php

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use SmartAlloc\Infra\Metrics\MetricsCollector;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Observability metrics endpoint for export operations.
 */
final class MetricsController
{
    public function __construct(private MetricsCollector $collector)
    {
    }

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
                    '/metrics',
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

        $cache_key = 'smartalloc_metrics_cache';
        $data = get_transient($cache_key);
        if ($data === false) {
            $data = $this->collector->all();
            set_transient($cache_key, $data, 60);
        }

        return new WP_REST_Response($data, 200);
    }
}

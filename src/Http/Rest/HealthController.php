<?php

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Simple healthcheck endpoint.
 */
final class HealthController
{
    /**
     * Register REST route.
     */
    public function register_routes(): void
    {
        add_action(
            'rest_api_init',
            function (): void {
                register_rest_route(
                    'smartalloc/v1',
                    '/health',
                    array(
                        'methods'             => 'GET',
                        'permission_callback' => '__return_true',
                        'callback'            => array($this, 'handle'),
                    )
                );
            }
        );
    }

    /**
     * Handle health check request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function handle(WP_REST_Request $request)
    {
        global $wpdb;

        $db_ok = false;
        if (isset($wpdb)) {
            $db_ok = (bool) $wpdb->get_var($wpdb->prepare('SELECT 1'));
        }

        wp_cache_set('smartalloc_health', 1, '', 30);
        $cache_ok = (int) wp_cache_get('smartalloc_health', '') === 1;

        $version        = (string) get_option('smartalloc_version');
        $last_migration = get_option('smartalloc_last_migration');

        $response = array(
            'ok'             => $db_ok && $cache_ok,
            'version'        => $version ?: 'unknown',
            'db'             => $db_ok,
            'cache'          => $cache_ok,
            'last_migration' => $last_migration ?: null,
            'notes'          => array(),
        );

        return new WP_REST_Response($response, 200);
    }
}

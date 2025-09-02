<?php
// @security-ok-rest

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use SmartAlloc\Security\RateLimiter;
use SmartAlloc\Services\NotificationThrottler;
use SmartAlloc\Services\DLQMetrics;

/**
 * Simple healthcheck endpoint.
 */
final class HealthController
{
    public function __construct(private RateLimiter $limiter = new RateLimiter())
    {
    }

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
     * Handle health check request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function handle(WP_REST_Request $request)
    {
        unset($request); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        if (!current_user_can(SMARTALLOC_CAP)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }
        $error = $this->limiter->enforce('health', get_current_user_id());
        if ($error) {
            return $error;
        }

        global $wpdb;

        $db_ok = false;
        if (isset($wpdb)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $db_ok = (bool) $wpdb->get_var($wpdb->prepare('SELECT 1'));
        }

        wp_cache_set('smartalloc_health', 1, '', 30); // phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.LowCacheTime
        $cache_ok = (int) wp_cache_get('smartalloc_health', '') === 1;

        $version        = (string) get_option('smartalloc_version');
        $last_migration = get_option('smartalloc_last_migration');

        $notes = array();
        if (class_exists('SmartAlloc\\Infra\\Logging\\Logger')) {
            $req = \SmartAlloc\Infra\Logging\Logger::requestId();
            $notes['request'] = substr(hash('sha256', $req), 0, 8);
        }

        $throttler = new NotificationThrottler();
        $dlq       = new DLQMetrics();
        $response  = array(
            'ok'             => $db_ok && $cache_ok,
            'version'        => $version ?: 'unknown',
            'db'             => $db_ok,
            'cache'          => $cache_ok,
            'last_migration' => $last_migration ?: null,
            'notes'          => $notes,
            'metrics'        => array(
                'throttle' => $throttler->getThrottleStats(),
                'dlq'      => $dlq->getMetrics(),
            ),
        );

        return new WP_REST_Response($response, 200);
    }
}

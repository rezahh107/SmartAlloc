<?php

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use SmartAlloc\Infra\Export\ExporterService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for generating exports.
 */
final class ExportController
{
    public function __construct(private ExporterService $service)
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
                    '/export',
                    array(
                        'methods'             => 'POST',
                        'permission_callback' => function (): bool {
                            return current_user_can( SMARTALLOC_CAP );
                        },
                        'callback'            => array( $this, 'handle' ),
                    )
                );
            }
        );
    }

    /**
     * Handle export generation request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function handle( WP_REST_Request $request )
    {
        if ( ! current_user_can( SMARTALLOC_CAP ) ) {
            return new WP_Error( 'forbidden', 'Forbidden', array( 'status' => 403 ) );
        }

        $params = $request->get_json_params();
        $from   = sanitize_text_field( $params['from'] ?? '' );
        $to     = sanitize_text_field( $params['to'] ?? '' );
        $batch  = isset( $params['batch'] ) ? absint( $params['batch'] ) : null;

        if ( $batch === null ) {
            if ( ! $this->valid_date( $from ) || ! $this->valid_date( $to ) ) {
                return new WP_Error( 'invalid_payload', 'Invalid date range', array( 'status' => 400 ) );
            }
        } elseif ( $batch <= 0 ) {
            return new WP_Error( 'invalid_payload', 'Invalid batch', array( 'status' => 400 ) );
        }

        // Per-user rate limiting: default 3 exports per 10 minutes.
        $user_id   = get_current_user_id();
        $limit     = (int) get_option( 'export_rate_limit', 3 );
        $rate_key  = 'smartalloc_export_rate_' . $user_id;
        $rate_cnt  = (int) get_transient( $rate_key );
        if ( $rate_cnt >= $limit ) {
            // Retry-After header in seconds.
            header( 'Retry-After: 600' );
            return new WP_Error( 'rate_limited', 'Rate limit exceeded', array( 'status' => 429 ) );
        }
        set_transient( $rate_key, $rate_cnt + 1, 10 * MINUTE_IN_SECONDS );

        $lock_key = sprintf( 'export:%s:%s:%s', $from, $to, $batch ?? 'none' );
        if ( get_transient( $lock_key ) ) {
            return new WP_Error( 'conflict', 'Export in progress', array( 'status' => 409 ) );
        }
        set_transient( $lock_key, 1, 10 * MINUTE_IN_SECONDS );

        try {
            $result = $this->service->generate( $from, $to, $batch );
        } catch ( \Throwable $e ) {
            delete_transient( $lock_key );
            return new WP_Error( 'export_failed', 'Export failed', array( 'status' => 500 ) );
        }

        delete_transient( $lock_key );

        return new WP_REST_Response( $result, 200 );
    }

    private function valid_date( string $date ): bool
    {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return false;
        }
        [ $y, $m, $d ] = array_map( 'intval', explode( '-', $date ) );
        if ( function_exists( 'wp_checkdate' ) ) {
            return wp_checkdate( $m, $d, $y, $date );
        }
        return checkdate( $m, $d, $y );
    }
}

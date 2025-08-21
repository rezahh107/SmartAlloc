<?php
// @security-ok-rest

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use SmartAlloc\Infra\Export\ExporterService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for listing recent exports.
 */
final class ExportsListController
{
    public function __construct(private ExporterService $service)
    {
    }

    public function register_routes(): void
    {
        add_action(
            'rest_api_init',
            function (): void {
                register_rest_route(
                    'smartalloc/v1',
                    '/exports',
                    array(
                        'methods'             => 'GET',
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
     * Handle list request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function handle( WP_REST_Request $request )
    {
        if ( ! current_user_can( SMARTALLOC_CAP ) ) {
            return new WP_Error( 'forbidden', 'Forbidden', array( 'status' => 403 ) );
        }
        $limit = (int) $request->get_param( 'limit' );
        if ( $limit <= 0 ) {
            $limit = 20;
        }
        try {
            $rows = $this->service->getRecent( $limit );
        } catch ( \Throwable $e ) {
            return new WP_Error( 'export_failed', 'Export list failed', array( 'status' => 500 ) );
        }
        return new WP_REST_Response( $rows, 200 );
    }
}

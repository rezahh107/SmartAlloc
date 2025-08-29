<?php

declare(strict_types=1);

namespace SmartAlloc\REST\Controllers;

// phpcs:ignoreFile
use WP_REST_Request;
use WP_REST_Response;

final class OverrideController {

        public function __construct( private $svc ) {}

	public function register(): void {
		$cb = function () {
			register_rest_route(
				'smartalloc/v1',
				'/allocations/(?P<id>\d+)/override',
				array(
					'methods'             => 'POST',
					'permission_callback' => function ( WP_REST_Request $r ): bool {
						return current_user_can( SMARTALLOC_CAP ) &&
							wp_verify_nonce( (string) $r->get_header( 'X-WP-Nonce' ), 'wp_rest' );
					},
					'callback'            => array( $this, 'handle' ),
				)
			);
		};
		add_action( 'rest_api_init', $cb );
		if ( defined( 'PHPUNIT_RUNNING' ) && PHPUNIT_RUNNING ) {
			$cb();
		}
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$id     = absint( $request['id'] );
		$mentor = absint( $request->get_param( 'mentor_id' ) );
		$notes  = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );
		$result = $this->svc->override( $id, $mentor, $notes );
		return new WP_REST_Response( $result, 200 );
	}
}

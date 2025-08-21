<?php
// @security-ok-rest

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use SmartAlloc\Services\AllocationService;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\LoggerInterface;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller handling allocation requests.
 */
final class AllocationController {
	public function __construct(
		private AllocationService $allocator,
		private EventBus $event_bus,
		private LoggerInterface $logger
	) {
	}

	/**
	 * Register REST route for allocation.
	 */
	public function register_routes(): void {
		add_action(
			'rest_api_init',
			function (): void {
				register_rest_route(
					'smartalloc/v1',
                                        '/allocate',
                                        array(
                                                'methods'             => 'POST',
                                                'permission_callback' => function ( WP_REST_Request $request ): bool {
                                                        return current_user_can( SMARTALLOC_CAP ) &&
                                                                wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
                                                },
                                                'callback'            => array( $this, 'handle' ),
                                        )
                                );
                        }
                );
	}

	/**
	 * Handle allocation request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		if ( ! current_user_can( SMARTALLOC_CAP ) ) {
			return new WP_Error( 'forbidden', 'Forbidden', array( 'status' => 403 ) );
		}

		$data = json_decode( $request->get_body(), true );
		if ( ! is_array( $data ) || ! isset( $data['student'] ) || ! is_array( $data['student'] ) ) {
			return new WP_Error( 'invalid_payload', 'Invalid payload', array( 'status' => 400 ) );
		}

		$student = $data['student'];
		$id      = absint( $student['id'] ?? 0 );
		$center  = absint( $student['center'] ?? 0 );
		$gender  = sanitize_text_field( $student['gender'] ?? '' );
		$group   = sanitize_text_field( $student['group_code'] ?? '' );
		$schools = array_map( 'absint', $student['schools'] ?? array() );

		if ( $id <= 0 || $center <= 0 ) {
			return new WP_Error( 'invalid_payload', 'Invalid id or center', array( 'status' => 400 ) );
		}

		$allowed_gender = array( 'M', 'F' );
		$allowed_group  = array( 'EX', 'MA', 'HU', 'LA', 'AR' );
		if ( ! in_array( $gender, $allowed_gender, true ) || ! in_array( $group, $allowed_group, true ) ) {
			return new WP_Error( 'invalid_payload', 'Invalid gender or group_code', array( 'status' => 400 ) );
		}

		$student = array(
			'id'         => $id,
			'gender'     => $gender,
			'group_code' => $group,
			'center'     => $center,
			'schools'    => $schools,
		);

		$this->event_bus->dispatch( 'StudentSubmitted', $student );

		$result = $this->allocator->assign( $student );

		$this->logger->info(
			'rest.allocate',
			array(
				'student_id' => $id,
				'mentor_id'  => $result['mentor_id'] ?? null,
			)
		);

		return new WP_REST_Response(
			array(
				'ok'     => true,
				'result' => $result,
			),
			201
		);
	}
}

<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\REST\Controllers\OverrideController;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Tests\BaseTestCase;
use WP_REST_Request;

final class OverrideEndpointTest extends BaseTestCase {

	private OverrideController $controller;
	private $cb;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$svc              = new class() extends AllocationService {
			public array $last = array();
			public function __construct() {}
			public function override( int $a, int $m, string $n ): array {
				$this->last = array( $a, $m, $n );
				return array( 'ok' => 1 ); }
		};
		$this->controller = new OverrideController( $svc );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function register(): void {
		Functions\when( 'register_rest_route' )->alias(
			function ( $ns, $route, $args ) {
				$GLOBALS['sa_rest_routes'][ "$ns $route" ] = $args;
			}
		);
		$this->controller->register();
		$key      = 'smartalloc/v1 /allocations/(?P<id>\d+)/override';
		$this->cb = $GLOBALS['sa_rest_routes'][ $key ]['callback'];
	}

	/** @test */
	public function calls_service_on_valid_request(): void {
		Functions\expect( 'current_user_can' )->with( SMARTALLOC_CAP )->andReturn( true );
		Functions\expect( 'wp_verify_nonce' )->andReturn( true );
		$this->register();
		$req       = new WP_REST_Request( 'POST' );
		$req['id'] = 5;
		$req->set_param( 'mentor_id', 7 );
		$req->set_param( 'notes', 'x' );
		$res = ( $this->cb )( $req );
		$this->assertSame( 200, $res->get_status() );
	}
}

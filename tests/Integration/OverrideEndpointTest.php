<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\REST\Controllers\OverrideController;
use SmartAlloc\Tests\BaseTestCase;

if ( ! defined( 'SMARTALLOC_CAP' ) ) {
        define( 'SMARTALLOC_CAP', 'manage_options' );
}
if ( ! defined( 'PHPUNIT_RUNNING' ) ) {
        define( 'PHPUNIT_RUNNING', true );
}
class WP_REST_Request_Ext extends \WP_REST_Request implements \ArrayAccess {
        private array $params = array();
        public function set_param( string $key, $value ): void { $this->params[ $key ] = $value; }
        public function get_param( string $key ) { return $this->params[ $key ] ?? null; }
        public function offsetExists( $offset ): bool { return isset( $this->params[ $offset ] ); }
        public function offsetGet( $offset ) { return $this->params[ $offset ] ?? null; }
        public function offsetSet( $offset, $value ): void { $this->params[ $offset ] = $value; }
        public function offsetUnset( $offset ): void { unset( $this->params[ $offset ] ); }
}
class WP_REST_Response_Stub {
        public function __construct( public array $data = array(), public int $status = 200 ) {}
        public function get_status(): int { return $this->status; }
}
if ( ! class_exists( '\\WP_REST_Response' ) ) {
        class_alias( WP_REST_Response_Stub::class, 'WP_REST_Response' );
}

final class OverrideEndpointTest extends BaseTestCase {

        private OverrideController $controller;
        /** @var callable */
        private $cb;

        protected function setUp(): void {
                parent::setUp();
                Monkey\setUp();
                $svc              = new class() {
                        public array $last = array();
                        public function override( int $a, int $m, string $n ): array {
                                $this->last = array( $a, $m, $n );
                                return array( 'ok' => 1 );
                        }
                };
                $this->controller = new OverrideController( $svc );
        }

        protected function tearDown(): void {
                Monkey\tearDown();
                parent::tearDown();
        }

        private function register(): void {
                $GLOBALS['sa_rest_routes'] = array();
                Functions\when( 'register_rest_route' )->alias(
                        function ( $ns, $route, $args ) {
                                $GLOBALS['sa_rest_routes'][ "$ns $route" ] = $args;
                        }
                );
                $this->controller->register();
                $key      = 'smartalloc/v1 /allocations/(?P<id>\\d+)/override';
                $this->cb = $GLOBALS['sa_rest_routes'][ $key ]['callback'];
        }

        /** @test */
        public function calls_service_on_valid_request(): void {
                Functions\expect( 'current_user_can' )->with( SMARTALLOC_CAP )->andReturn( true );
                Functions\expect( 'wp_verify_nonce' )->andReturn( true );
                Functions\expect( 'sanitize_textarea_field' )->andReturnUsing( fn ( $x ) => $x );
                $this->register();
                $req       = new WP_REST_Request_Ext( 'POST' );
                $req->set_param( 'id', 5 );
                $req->set_param( 'mentor_id', 7 );
                $req->set_param( 'notes', 'x' );
                $res = ( $this->cb )( $req );
                $this->assertSame( 200, $res->get_status() );
        }
}

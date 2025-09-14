<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Tests\BaseTestCase;

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

final class AllocationServiceOverrideTest extends BaseTestCase {

	private AllocationService $svc;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		global $wpdb;
                $wpdb      = new class() extends \wpdb {
                        public $prefix = 'wp_';
                        public array $rows    = array(
                                1 => array(
					'id'        => 1,
					'mentor_id' => 2,
					'status'    => 'pending_review',
				),
			);
			public function __construct() {}
                        public function prepare( $q, ...$a ) {
                                return vsprintf( $q, $a[0] );
                        }
                        public function get_row( $q = null, $o = OBJECT, $y = 0 ) {
                                return $this->rows[1] ?? null;
                        }
			public function query( $q ) {
				if ( str_contains( $q, 'UPDATE' ) ) {
					$this->rows[1]['mentor_id']             = 3;
					$this->rows[1]['status']                = 'allocated';
					$this->rows[1]['overridden_by_user_id'] = 9;
					$this->rows[1]['override_notes']        = 'n';}
				return 1;
			}
		};
		$this->svc = new AllocationService( new TableResolver( $wpdb ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** @test */
	public function overrides_successfully(): void {
		Functions\expect( 'get_current_user_id' )->andReturn( 9 );
		Functions\expect( 'get_user_by' )->andReturn( (object) array( 'ID' => 3 ) );
		$res = $this->svc->override( 1, 3, 'n' );
		$this->assertSame( 3, $res['mentor_id'] );
	}

	/** @test */
	public function throws_when_mentor_missing(): void {
		Functions\expect( 'get_current_user_id' )->andReturn( 9 );
		Functions\expect( 'get_user_by' )->andReturn( false );
		$this->expectException( \RuntimeException::class );
		$this->svc->override( 1, 99, '' );
	}
}

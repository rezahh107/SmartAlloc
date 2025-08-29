<?php
// phpcs:ignoreFile

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Core\FormContext;

final class AllocationServiceTest extends TestCase {
	public function test_invalid_payload_returns_empty(): void {
		$wpdb = new class() extends \wpdb {
			public string $prefix = 'wp_';
			public function __construct() {}
		};
		$svc  = new AllocationService( new TableResolver( $wpdb ) );
		$res  = $svc->allocateWithContext( new FormContext( 150 ), array( 'email' => 'a@b.com' ) );
		$this->assertSame(
			array(
				'summary'     => array(
					'form_id' => 150,
					'count'   => 0,
				),
				'allocations' => array(),
			),
			$res
		);
	}
}

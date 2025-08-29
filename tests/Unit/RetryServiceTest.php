<?php
// phpcs:ignoreFile

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\RetryService;

class SpyAS {
	public array $last = array(
		'hook'  => null,
		'args'  => null,
		'delay' => null,
	);
        public function enqueue( string $hook, array $args = array(), int $delaySec = 0 ): void {
		$this->last = array(
			'hook'  => $hook,
			'args'  => $args,
			'delay' => $delaySec,
		);
	}
}

final class RetryServiceTest extends TestCase {
	public function test_attempt_is_incremented_before_enqueue(): void {
		$spy   = new SpyAS();
		$retry = new RetryService( $spy );
		$retry->enqueue( 'smartalloc_notify', array( array( '_attempt' => 1 ) ), 1 );
		$this->assertSame( 'smartalloc_notify', $spy->last['hook'] );
		$this->assertSame( 2, $spy->last['args'][0]['_attempt'] );
	}
}

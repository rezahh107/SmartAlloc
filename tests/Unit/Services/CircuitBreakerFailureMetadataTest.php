<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use SmartAlloc\Services\CircuitBreaker;

class ArrayLogger extends AbstractLogger {
	public array $records = array();
	public function log( $level, $message, array $context = array() ): void {
		$this->records[] = compact( 'level', 'message', 'context' );
	}
	public function hasWarning( string $message ): bool {
		foreach ( $this->records as $r ) {
			if ( $r['level'] === 'warning' && $r['message'] === $message ) {
				return true;
			}
		}
		return false;
	}
	public function hasError( string $message ): bool {
		foreach ( $this->records as $r ) {
			if ( $r['level'] === 'error' && $r['message'] === $message ) {
				return true;
			}
		}
		return false;
	}
}

final class CircuitBreakerFailureMetadataTest extends TestCase {
	private CircuitBreaker $cb;
	private ArrayLogger $logger;

	protected function setUp(): void {
		$this->logger = new ArrayLogger();
		$this->cb     = new CircuitBreaker( 'test', $this->logger, 5 );
	}

	public function testFailureRecordsMetadataAndLogging(): void {
		$e = new \RuntimeException( 'fail', 123 );
		$this->cb->failure( 'op', $e );
		$m = $this->cb->getFailureMetadata();
		$this->assertCount( 1, $m );
		$this->assertSame( 'op', $m[0]['operation_name'] );
		$this->assertTrue( $this->logger->hasWarning( 'Circuit breaker failure recorded' ) );
	}

        public function testMetadataSizeLimit(): void {
                $cb = new CircuitBreaker( 'limit', $this->logger, 2 );
                for ( $i = 0; $i < 4; $i++ ) {
                        $cb->failure( 'o' . $i, new \RuntimeException( (string) $i ) );
                }
		$m = $cb->getFailureMetadata();
		$this->assertCount( 2, $m );
		$this->assertSame( '3', $m[1]['exception_message'] );
	}

}

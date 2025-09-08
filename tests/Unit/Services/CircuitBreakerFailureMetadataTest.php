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
                $this->cb     = new CircuitBreaker(
                        failureThreshold: 5,
                        logger: $this->logger,
                        name: 'test'
                );
        }

        public function testFailureRecordsMetadataAndLogging(): void {
                try {
                        $this->cb->execute(
                                function (): void {
                                        throw new \RuntimeException('fail', 123);
                                }
                        );
                } catch ( \RuntimeException $e ) { // @phpstan-ignore catch.neverThrown
                        // expected
                }

                $metadata = $this->cb->getFailureMetadata();
                $this->assertCount( 1, $metadata );
                $first = reset( $metadata );
                $this->assertSame( 'RuntimeException', $first['exception_type'] );
                $this->assertTrue( $this->logger->hasWarning( 'Circuit breaker recorded failure' ) );
        }

        public function testMetadataStorageLimit(): void {
                $cb = new CircuitBreaker(
                        failureThreshold: 100,
                        logger: $this->logger,
                        name: 'limit'
                );

                for ( $i = 0; $i < 60; $i++ ) {
                        try {
                                $cb->execute(
                                        function () use ( $i ): void {
                                                throw new \RuntimeException( "Failure {$i}" );
                                        }
                                );
                        } catch ( \RuntimeException $e ) { // @phpstan-ignore catch.neverThrown
                                // expected
                        }
                }

                $metadata = $cb->getFailureMetadata();
                $this->assertLessThanOrEqual( 50, count( $metadata ) );
                $messages = array_column( $metadata, 'exception_message' );
                $this->assertContains( 'Failure 59', $messages );
                $this->assertNotContains( 'Failure 0', $messages );
        }

}

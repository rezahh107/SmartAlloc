<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit;

use SmartAlloc\Tests\Helpers\TestCase;
use SmartAlloc\Admin\DebugScreen;
use SmartAlloc\Health\HealthReporter;
use SmartAlloc\Services\CircuitBreaker;

if (! defined('WP_TESTS_DOMAIN')) {
    define('WP_TESTS_DOMAIN', 'example.org');
    define('WP_TESTS_EMAIL', 'admin@example.org');
    define('WP_TESTS_TITLE', 'Test Blog');
}

require_once __DIR__ . '/../bootstrap.php';

final class DebugScreenTest extends TestCase {

        private DebugScreen $debug_screen;
        private HealthReporter $health_reporter;

	protected function setUp(): void {
                parent::setUp();
                $cb                   = new CircuitBreaker( 'test' );
                $this->health_reporter = new HealthReporter( $cb );
                $this->debug_screen    = new DebugScreen( $this->health_reporter );
        }

        public function test_render_circuit_breaker_status_healthy(): void {
                $health_status = array(
                        'success' => true,
                        'data'    => array(
                                'status'        => 'healthy',
                                'circuit_state' => 'closed',
                                'failure_count' => 0,
                                'last_failure'  => null,
                                'next_retry'    => null,
                                'timestamp'     => '2025-01-09T10:30:00Z',
                        ),
                );
                set_transient( 'smartalloc_health_status', $health_status, 30 );

               ob_start();
               DebugScreen::render_circuit_breaker_status();
               $output = ob_get_clean();

                $this->assertStringContainsString( 'Circuit Breaker Status', $output );
                $this->assertStringContainsString( 'status-healthy', $output );
                $this->assertStringContainsString( 'State:</strong></td><td>Closed', $output );
                $this->assertStringContainsString( 'Failure Count:</strong></td><td>0', $output );
                $this->assertStringContainsString( 'Cooldown:</strong></td><td>N/A', $output );
        }

	public function test_format_countdown(): void {
		$reflection = new \ReflectionClass( $this->debug_screen );
		$method     = $reflection->getMethod( 'format_countdown' );
		$method->setAccessible( true );

		$this->assertSame( 'Ready', $method->invoke( $this->debug_screen, 0 ) );
		$this->assertSame( '30s', $method->invoke( $this->debug_screen, 30 ) );
		$this->assertSame( '1m 30s', $method->invoke( $this->debug_screen, 90 ) );
	}
}

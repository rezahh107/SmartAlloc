<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Tests\Integration;

use SmartAlloc\Admin\DebugScreen;
use SmartAlloc\Health\HealthReporter;
use SmartAlloc\Services\CircuitBreaker;
use WP_UnitTestCase;

if (! defined('WP_TESTS_DOMAIN')) {
    define('WP_TESTS_DOMAIN', 'example.org');
    define('WP_TESTS_EMAIL', 'admin@example.org');
    define('WP_TESTS_TITLE', 'Test Blog');
}

require_once __DIR__ . '/../bootstrap-integration.php';

final class DebugScreenIntegrationTest extends WP_UnitTestCase {

        private DebugScreen $debug_screen;
        private HealthReporter $health_reporter;

	public function setUp(): void {
                parent::setUp();
                $cb                   = new CircuitBreaker( 'integration' );
                $this->health_reporter = new HealthReporter( $cb );
                $this->debug_screen    = new DebugScreen( $this->health_reporter );
        }

        public function test_full_debug_screen_includes_circuit_breaker(): void {
                $health_status = array(
                        'success' => true,
                        'data'    => array(
                                'status'        => 'healthy',
                                'circuit_state' => 'closed',
                                'failure_count' => 0,
                                'last_failure'  => null,
                                'next_retry'    => null,
                        ),
                );
                set_transient( 'smartalloc_health_status', $health_status, 30 );

                ob_start();
                $this->debug_screen->render();
                $output = ob_get_clean();

                $this->assertStringContainsString( 'Circuit Breaker Status', $output );
                $this->assertStringContainsString( 'status-healthy', $output );
                $this->assertStringContainsString( 'State:</strong></td><td>Closed', $output );
        }
}

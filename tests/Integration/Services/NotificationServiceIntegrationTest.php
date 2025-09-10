<?php
declare(strict_types=1);
// phpcs:ignoreFile
// phpstan:ignoreFile

use SmartAlloc\Services\{NotificationService, CircuitBreaker, Logging, DlqService};
use SmartAlloc\Tests\Helpers\SpyMetrics;
use SmartAlloc\Exceptions\ThrottleException;
use SmartAlloc\Http\Rest\HealthController;
use SmartAlloc\Security\RateLimiter;
use PHPUnit\Framework\TestCase;
use SmartAlloc\Infrastructure\Contracts\DlqRepository;
use Brain\Monkey;
use Brain\Monkey\Functions;

require_once __DIR__ . '/../../mocks/WpdbMock.php';

final class NotificationServiceIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'as_enqueue_single_action' )->justReturn( true );
        $GLOBALS['wpdb'] = new WpdbMock();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_throttle_stats_exposed_in_health(): void
    {
        global $t, $o; $t = $o = array();
        $svc = new NotificationService( new CircuitBreaker(), new Logging(), new SpyMetrics() );
        for ( $i = 0; $i < 11; $i++ ) {
            if ( 3 === $i ) { global $t; $t['smartalloc_notify_rate'] = 0; }
            try {
                $svc->send(
                    array(
                        'event_name' => 'user_registered',
                        'body'      => array( 'user_id' => $i ),
                        'recipient' => 'r',
                    )
                );
            } catch ( \Throwable $e ) {}
        }
        $ctrl  = new HealthController( new RateLimiter() );
        $data  = $ctrl->handle( new WP_REST_Request() )->get_data();
        $stats = get_option( 'smartalloc_throttle_stats' );
        $this->assertGreaterThan( 0, $stats['hits'] );
        $this->assertArrayHasKey( 'dlq', $data['metrics'] );
    }

    public function test_success_sends_and_masks_pii(): void
    {
        global $t, $o, $wpdb; $wpdb = new WpdbMock(); $t = $o = array(); $wpdb->records = array();
        $tmp = tempnam( sys_get_temp_dir(), 'log' );
        add_filter( 'smartalloc_log_path', fn( $p ) => $tmp );
        $repo = new class implements DlqRepository {
            public array $items = array();
            public function insert( string $topic, array $payload, \DateTimeImmutable $created_at_utc ): bool { $this->items[] = array( 'topic' => $topic, 'payload' => $payload ); return true; }
            public function listRecent( int $limit ): array { return array(); }
            public function get( int $id ): ?array { return null; }
            public function delete( int $id ): bool { return true; }
            public function count(): int { return count( $this->items ); }
        };
        $svc = new NotificationService( new CircuitBreaker(), new Logging(), new SpyMetrics(), null, new DlqService( $repo ) );
        $svc->handle(
            array(
                'event_name' => 'user_registered',
                'body'       => array( 'email' => 'foo@example.com', 'user_id' => 123 ),
            )
        );
        $counts = array_count_values( array_column( $wpdb->records, 'metric_key' ) );
        $this->assertSame( 1, $counts['notify_success_total'] ?? 0 );
        $log = file_get_contents( $tmp );
        if ( false === $log ) {
            $log = '';
        }
        $this->assertStringNotContainsString( 'foo@example.com', $log );
        $this->assertStringNotContainsString( '123', $log );
    }

    public function test_failure_pushes_to_dlq_and_masks_pii(): void
    {
        global $t, $o, $wpdb; $wpdb = new WpdbMock(); $t = $o = array(); $wpdb->records = array();
        $tmp = tempnam( sys_get_temp_dir(), 'log' );
        add_filter( 'smartalloc_log_path', fn( $p ) => $tmp );
        if ( ! defined( 'SMARTALLOC_NOTIFY_MAX_TRIES' ) ) {
            define( 'SMARTALLOC_NOTIFY_MAX_TRIES', 1 );
        }
        $repo = new class implements DlqRepository {
            public array $items = array();
            public function insert( string $topic, array $payload, \DateTimeImmutable $created_at_utc ): bool { $this->items[] = array( 'topic' => $topic, 'payload' => $payload ); return true; }
            public function listRecent( int $limit ): array { return array(); }
            public function get( int $id ): ?array { return null; }
            public function delete( int $id ): bool { return true; }
            public function count(): int { return count( $this->items ); }
        };
        $svc = new NotificationService( new CircuitBreaker(), new Logging(), new SpyMetrics(), null, new DlqService( $repo ) );
        add_filter( 'smartalloc_notify_transport', fn( $r, $body, $attempt ) => 'fail', 10, 3 );
        $svc->handle(
            array(
                'event_name' => 'user_registered',
                'body'       => array( 'email' => 'foo@example.com', 'user_id' => 123 ),
            )
        );
        $counts = array_count_values( array_column( $wpdb->records, 'metric_key' ) );
        $this->assertSame( 1, $counts['notify_failed_total'] ?? 0 );
        $log = file_get_contents( $tmp );
        if ( false === $log ) {
            $log = '';
        }
        $this->assertStringNotContainsString( 'foo@example.com', $log );
        $this->assertStringNotContainsString( '123', $log );
    }

    public function test_rate_limit_pushes_sanitized_payload_to_dlq(): void
    {
        global $t, $o, $wpdb; $wpdb = new WpdbMock(); $t = array( 'smartalloc_notify_rate' => 10 ); $o = array(); $wpdb->records = array();
        $repo = new class implements DlqRepository {
            public array $items = array();
            public function insert( string $topic, array $payload, \DateTimeImmutable $created_at_utc ): bool { $this->items[] = array( 'topic' => $topic, 'payload' => $payload ); return true; }
            public function listRecent( int $limit ): array { return array(); }
            public function get( int $id ): ?array { return null; }
            public function delete( int $id ): bool { return true; }
            public function count(): int { return count( $this->items ); }
        };
        $svc = new NotificationService( new CircuitBreaker(), new Logging(), new SpyMetrics(), null, new DlqService( $repo ) );
        try {
            $svc->send(
                array(
                    'event_name' => 'user_registered',
                    'body'       => array( 'email' => 'foo@example.com', 'user_id' => 123 ),
                    'recipient'  => 'foo@example.com',
                )
            );
            $this->fail( 'ThrottleException not thrown' );
        } catch ( ThrottleException $e ) {
            $counts = array_count_values( array_column( $wpdb->records, 'metric_key' ) );
            $this->assertSame( 1, $counts['notify_throttled_total'] ?? 0 );
            $this->assertSame( 1, $counts['dlq_push_total'] ?? 0 );
            $this->assertSame( 1, $counts['notify_failed_total'] ?? 0 );
            $this->assertSame( '[REDACTED]', $repo->items[0]['payload']['body']['email'] ?? '' );
            $this->assertStringStartsWith( 'user_', $repo->items[0]['payload']['body']['user_id'] ?? '' );
        }
    }
}

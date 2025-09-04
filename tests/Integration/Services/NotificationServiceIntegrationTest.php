<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Services {
    function apply_filters( $tag, $value, ...$args ) {
        return isset( $GLOBALS['filters'][ $tag ] ) ? $GLOBALS['filters'][ $tag ]( $value, ...$args ) : $value;
    }
}

namespace {
use SmartAlloc\Services\{NotificationService, CircuitBreaker, Logging, Metrics, DlqService};
use SmartAlloc\Http\Rest\HealthController;
use SmartAlloc\Security\RateLimiter;
use PHPUnit\Framework\TestCase;
use SmartAlloc\Infrastructure\Contracts\DlqRepository;

if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'get_transient' ) ) { function get_transient( $k ) { global $t; return $t[ $k ] ?? false; } }
if ( ! function_exists( 'set_transient' ) ) { function set_transient( $k, $v, $e ) { global $t; $t[ $k ] = $v; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $k, $d = false ) { global $o; return $o[ $k ] ?? $d; } }
if ( ! function_exists( 'update_option' ) ) { function update_option( $k, $v ) { global $o; $o[ $k ] = $v; } }
if ( ! function_exists( 'wp_cache_set' ) ) { function wp_cache_set( $k, $v, $g, $t ) {} }
if ( ! function_exists( 'wp_cache_get' ) ) { function wp_cache_get( $k, $g ) { return 1; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( $c ) { return true; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $data ) { return json_encode( $data ); } }
if ( ! defined( 'SMARTALLOC_CAP' ) ) { define( 'SMARTALLOC_CAP', 'manage_options' ); }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
if ( ! class_exists( 'WP_REST_Request' ) ) { class WP_REST_Request {} }
if ( ! class_exists( 'WP_REST_Response' ) ) { class WP_REST_Response { public function __construct( private array $d = array(), private int $s = 200 ) {} public function get_data() { return $this->d; } } }
if ( ! class_exists( 'WP_Error' ) ) { class WP_Error { public function __construct( public string $c = '', public string $m = '', public array $d = array() ) {} } }
if ( ! function_exists( 'as_enqueue_single_action' ) ) { function as_enqueue_single_action() { return true; } }

require_once __DIR__ . '/../../mocks/WpdbMock.php';

final class NotificationServiceIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wpdb'] = new WpdbMock();
    }

    public function test_throttle_stats_exposed_in_health(): void
    {
        global $t, $o; $t = $o = array();
        $svc = new NotificationService( new CircuitBreaker(), new Logging(), new Metrics() );
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
        $GLOBALS['filters']['smartalloc_log_path'] = fn( $p ) => $tmp;
        $repo = new class implements DlqRepository {
            public array $items = array();
            public function insert( string $topic, array $payload, \DateTimeImmutable $created_at_utc ): void { $this->items[] = array( 'topic' => $topic, 'payload' => $payload ); }
            public function listRecent( int $limit ): array { return array(); }
            public function get( int $id ): ?array { return null; }
            public function delete( int $id ): void {}
            public function count(): int { return count( $this->items ); }
        };
        $svc = new NotificationService( new CircuitBreaker(), new Logging(), new Metrics(), null, new DlqService( $repo ) );
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
        $GLOBALS['filters']['smartalloc_log_path'] = fn( $p ) => $tmp;
        if ( ! defined( 'SMARTALLOC_NOTIFY_MAX_TRIES' ) ) {
            define( 'SMARTALLOC_NOTIFY_MAX_TRIES', 1 );
        }
        $repo = new class implements DlqRepository {
            public array $items = array();
            public function insert( string $topic, array $payload, \DateTimeImmutable $created_at_utc ): void { $this->items[] = array( 'topic' => $topic, 'payload' => $payload ); }
            public function listRecent( int $limit ): array { return array(); }
            public function get( int $id ): ?array { return null; }
            public function delete( int $id ): void {}
            public function count(): int { return count( $this->items ); }
        };
        $svc = new NotificationService( new CircuitBreaker(), new Logging(), new Metrics(), null, new DlqService( $repo ) );
        $GLOBALS['filters']['smartalloc_notify_transport'] = fn( $r, $body, $attempt ) => 'fail';
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
}
}

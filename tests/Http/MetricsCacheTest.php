<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use SmartAlloc\Http\Rest\MetricsController;
use SmartAlloc\Tests\BaseTestCase;

if (!class_exists('WP_Error')) {
    class WP_Error { public function __construct(public string $code = '', public string $msg = '', public array $data = []) {} public function get_error_code(): string { return $this->code; } }
}
if (!class_exists('WP_REST_Request')) { class WP_REST_Request { public function get_params(): array { return []; } } }
if (!class_exists('WP_REST_Response')) { class WP_REST_Response { public function __construct(private array $d=[], private int $s=200) {} public function get_data(): array { return $this->d; } public function get_status(): int { return $this->s; } } }

final class MetricsCacheTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('current_user_can')->justReturn(true);
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public int $calls = 0;
            public function prepare($sql, $values = []) { return $sql; }
            public function get_results($sql, $output) { $this->calls++; return [[ 'grp' => '2025-01-01', 'auto_count'=>1, 'manual_count'=>0, 'reject_count'=>0, 'fuzzy_auto'=>0, 'fuzzy_manual'=>0, 'assigned'=>0, 'capacity'=>0 ]]; }
        };
    }

    public function test_caches_results(): void
    {
        $c = new MetricsController();
        $_GET = ['date_from'=>'2025-01-01','date_to'=>'2025-01-02'];
        $c->handle(new WP_REST_Request());
        $c->handle(new WP_REST_Request());
        global $wpdb; $this->assertSame(1, $wpdb->calls);
    }

    public function test_range_guard(): void
    {
        $c = new MetricsController();
        $_GET = ['date_from'=>'2025-01-01','date_to'=>'2025-12-31'];
        $res = $c->handle(new WP_REST_Request());
        $this->assertInstanceOf(WP_Error::class, $res);
        $this->assertSame('range_too_large', $res->get_error_code());
    }
}

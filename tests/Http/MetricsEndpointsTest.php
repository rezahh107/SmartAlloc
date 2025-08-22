<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;

require_once __DIR__ . '/../../scripts/metrics-endpoints.php';

if (!class_exists('WP_Error')) {
    class WP_Error { public function __construct(public string $c='', public string $m='', public array $d=[]){} public function get_error_data(): array { return $this->d; } }
}
if (!class_exists('WP_REST_Request')) { class WP_REST_Request { public function get_param($k){return null;} } }
if (!class_exists('WP_REST_Response')) { class WP_REST_Response { public function __construct(private array $d=[], private int $s=200){} public function get_data(){return $this->d;} public function get_status(){return $this->s;} } }

final class MetricsEndpointsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }
    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_requires_capability(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        $res = sa_metrics_handle(new WP_REST_Request());
        $this->assertInstanceOf(WP_Error::class, $res);
        $this->assertSame(403, $res->get_error_data()['status']);
    }

    public function test_returns_sorted_metrics(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public array $vars = [100, 5, 1, 2, 3];
            public array $results = [
                [ ['mentor_id'=>2,'assigned'=>20,'capacity'=>70], ['mentor_id'=>1,'assigned'=>10,'capacity'=>50] ],
                [ ['center_id'=>10,'assigned'=>30,'capacity'=>120] ],
                [ ['rule'=>'national_id_checksum','field'=>143,'count'=>2] ],
            ];
            public function get_var($sql){ return array_shift($this->vars); }
            public function get_results($sql,$output){ return array_shift($this->results); }
        };
        $data = sa_metrics_handle(new WP_REST_Request())->get_data();
        $this->assertSame(100, $data['allocations']['total']);
        $this->assertSame(1, $data['allocations']['by_mentor'][0]['mentor_id']);
        $this->assertSame(50, $data['allocations']['by_mentor'][0]['capacity']);
        $this->assertSame(60, $data['allocations']['by_mentor'][1]['capacity']);
        $this->assertSame('national_id_checksum', $data['validation_errors'][0]['rule']);
        $this->assertMatchesRegularExpression('/T.*Z/', $data['timestamp_utc']);
    }
}

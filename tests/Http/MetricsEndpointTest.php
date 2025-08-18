<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Http\Rest\MetricsController;

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
        public function get_error_data(): array { return $this->data; }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request { public function get_params(): array { return []; } }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct(private array $data = [], private int $status = 200) {}
        public function get_data(): array { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

final class MetricsEndpointTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public array $results = [];
            public function prepare($sql, $values = []) { return $sql; }
            public function get_results($sql, $output) { return array_shift($this->results); }
        };
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_requires_capability(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        $_GET = [];
        $GLOBALS['sa_options']['smartalloc_settings']['metrics_cache_ttl'] = 0;
        $controller = new MetricsController();
        $response = $controller->handle(new WP_REST_Request());
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame(403, $response->get_error_data()['status']);
    }

    public function test_metrics_returns_aggregates_for_date_range(): void
    {
        Functions\expect('current_user_can')->andReturn(true);
        global $wpdb;
        $wpdb->results = [[[
            'grp' => '2025-01-01',
            'auto_count' => 2,
            'manual_count' => 1,
            'reject_count' => 1,
            'fuzzy_auto' => 0,
            'fuzzy_manual' => 0,
            'assigned' => 0,
            'capacity' => 0,
        ]]];

        $controller = new MetricsController();
        $_GET = ['date_from' => '2025-01-01', 'date_to' => '2025-01-31'];
        $response = $controller->handle(new WP_REST_Request());
        $_GET = [];
        $data = $response->get_data();

        $this->assertSame(2, $data['rows'][0]['allocated']);
        $this->assertSame(1, $data['rows'][0]['manual']);
        $this->assertSame(1, $data['rows'][0]['reject']);
        $this->assertSame(2, $data['total']['allocated']);
    }

    public function test_group_by_center_and_by_day(): void
    {
        Functions\expect('current_user_can')->andReturn(true);
        global $wpdb;
        $wpdb->results = [
            [
                ['grp' => '1', 'auto_count' => 1, 'manual_count' => 0, 'reject_count' => 0,
                 'fuzzy_auto' => 0, 'fuzzy_manual' => 0, 'assigned' => 0, 'capacity' => 0]
            ],
            [
                ['grp' => '2025-01-01', 'auto_count' => 1, 'manual_count' => 0, 'reject_count' => 0,
                 'fuzzy_auto' => 0, 'fuzzy_manual' => 0, 'assigned' => 0, 'capacity' => 0]
            ],
        ];
        $controller = new MetricsController();

        $_GET = ['group_by' => 'center'];
        $resp1 = $controller->handle(new WP_REST_Request());
        $row1  = $resp1->get_data()['rows'][0];
        $this->assertArrayHasKey('center', $row1);

        $_GET = ['group_by' => 'day'];
        $resp2 = $controller->handle(new WP_REST_Request());
        $row2  = $resp2->get_data()['rows'][0];
        $this->assertArrayHasKey('date', $row2);
        $_GET = [];
    }

      public function test_handles_zero_division_and_empty_ranges(): void
      {
          Functions\expect('current_user_can')->andReturn(true);
          global $wpdb;
          $wpdb->results = [[]];
          $GLOBALS['sa_options']['smartalloc_settings']['metrics_cache_ttl'] = 0;
          $controller = new MetricsController();
          $_GET = ['group_by' => 'day'];
          $data = $controller->handle(new WP_REST_Request())->get_data();
          $_GET = [];
          $this->assertSame([], $data['rows']);
        $this->assertSame(0, $data['total']['allocated']);
        $this->assertSame(0.0, $data['total']['capacity_used']);
    }
}

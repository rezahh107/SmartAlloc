<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Infra\Metrics\MetricsCollector;
use SmartAlloc\Http\Rest\MetricsController;
use SmartAlloc\Tests\BaseTestCase;

if (!class_exists('WP_REST_Request')) { class WP_REST_Request { public function __construct(private array $p = []){} public function get_json_params(): array { return $this->p; } } }
if (!class_exists('WP_REST_Response')) { class WP_REST_Response { public function __construct(private array $d = [], private int $s = 200){} public function get_data(): array { return $this->d; } public function get_status(): int { return $this->s; } } }
if (!class_exists('WP_Error')) { class WP_Error { public function __construct(public string $c='', public string $m='', public array $d=[]){} public function get_error_data(): array { return $this->d; } } }

final class ExportMetricsEndpointTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $GLOBALS['sa_transients'] = [];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_requires_capability(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        $controller = new MetricsController(new MetricsCollector());
        $res = $controller->handle(new WP_REST_Request());
        $this->assertInstanceOf(WP_Error::class, $res);
        $this->assertSame(403, $res->get_error_data()['status']);
    }

    public function test_returns_metrics_and_caches(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        $collector = new MetricsCollector();
        $collector->reset();
        $collector->inc('exports_total');
        $controller = new MetricsController($collector);
        $r1 = $controller->handle(new WP_REST_Request());
        $this->assertSame(1, $r1->get_data()['counters']['exports_total']);
        $collector->inc('exports_total');
        $r2 = $controller->handle(new WP_REST_Request());
        $this->assertSame(1, $r2->get_data()['counters']['exports_total']);
    }
}

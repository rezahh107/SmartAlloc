<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Http\Rest\ExportController;
use SmartAlloc\Infra\Export\ExporterService;
use SmartAlloc\Infra\Metrics\MetricsCollector;
use SmartAlloc\Domain\Export\CircuitBreaker;
use SmartAlloc\Tests\BaseTestCase;


final class ExportControllerTest extends BaseTestCase
{
    private array $transients = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->transients = [];
        $GLOBALS['sa_options'] = [];
        Functions\when('get_current_user_id')->justReturn(1);
        $GLOBALS['sa_transients'] = &$this->transients;
        Functions\when('header')->alias(fn($h) => null);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_export_requires_capability(): void
    {
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(false);
        $service = new class extends ExporterService { public function __construct() {} };
        $collector = new MetricsCollector();
        $breaker = new CircuitBreaker($collector);
        $controller = new ExportController($service, $collector, $breaker);
        $request = new WP_REST_Request();
        $request->set_body(json_encode([]));
        $response = $controller->handle($request);
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame(403, $response->get_error_data()['status']);
    }

    public function test_export_returns_payload(): void
    {
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\when('wp_checkdate')->alias(fn($m,$d,$y,$date) => checkdate($m,$d,$y));

        $collector = new MetricsCollector();
        $service = new class($collector) extends ExporterService {
            public function __construct(private MetricsCollector $m) {}
            public function generate(string $from, string $to, ?int $batch = null): array {
                $this->m->inc('exports_total');
                return ['file' => '/tmp/file.xlsx', 'url' => 'http://example.com/file.xlsx', 'rows_exported' => 10];
            }
        };
        $breaker   = new CircuitBreaker($collector);
        $controller = new ExportController($service, $collector, $breaker);
        $request = new WP_REST_Request();
        $request->set_body(json_encode(['from' => '2024-01-01', 'to' => '2024-01-02']));
        $response = $controller->handle($request);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('http://example.com/file.xlsx', $data['url']);
        $this->assertSame(10, $data['rows_exported']);
        $this->assertSame(1, $collector->all()['counters']['exports_total'] ?? 0);
    }

    public function test_rate_limit_enforced(): void
    {
        Functions\expect('current_user_can')->times(4)->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\when('wp_checkdate')->alias(fn($m,$d,$y,$date) => checkdate($m,$d,$y));
        $service = new class extends ExporterService {
            public function __construct() {}
            public function generate(string $from, string $to, ?int $batch = null): array {
                return ['file' => 'f', 'url' => 'u', 'rows_exported' => 1];
            }
        };
        $collector = new MetricsCollector();
        $breaker = new CircuitBreaker($collector);
        $controller = new ExportController($service, $collector, $breaker);
        $request = new WP_REST_Request();
        $request->set_body(json_encode(['from'=>'2024-01-01','to'=>'2024-01-02']));
        for ($i = 0; $i < 3; $i++) {
            $resp = $controller->handle($request);
            $this->assertInstanceOf(WP_REST_Response::class, $resp);
        }
        $resp = $controller->handle($request);
        $this->assertInstanceOf(WP_Error::class, $resp);
        $this->assertSame(429, $resp->get_error_data()['status']);
        $metrics = $collector->all();
        $this->assertSame(1, $metrics['counters']['rate_limit_hit']);
    }

    public function test_lock_prevents_duplicates(): void
    {
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\when('wp_checkdate')->alias(fn($m,$d,$y,$date) => checkdate($m,$d,$y));
        $service = new class extends ExporterService {
            public function __construct() {}
            public function generate(string $from, string $to, ?int $batch = null): array {
                return ['file' => 'f', 'url' => 'u', 'rows_exported' => 1];
            }
        };
        $collector = new MetricsCollector();
        $breaker = new CircuitBreaker($collector);
        $controller = new ExportController($service, $collector, $breaker);
        $request = new WP_REST_Request();
        $request->set_body(json_encode(['from'=>'2024-01-01','to'=>'2024-01-02']));
        $this->transients['export:2024-01-01:2024-01-02:none'] = 1;
        $resp = $controller->handle($request);
        $this->assertInstanceOf(WP_Error::class, $resp);
        $this->assertSame(409, $resp->get_error_data()['status']);
        $metrics = $collector->all();
        $this->assertSame(1, $metrics['counters']['locks_hit']);
    }

    public function test_failure_records_metrics(): void
    {
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\when('wp_checkdate')->alias(fn($m,$d,$y,$date) => checkdate($m,$d,$y));
        $service = new class extends ExporterService {
            public function __construct() {}
            public function generate(string $from, string $to, ?int $batch = null): array {
                throw new \RuntimeException('boom');
            }
        };
        $collector = new MetricsCollector();
        $breaker = new CircuitBreaker($collector);
        $controller = new ExportController($service, $collector, $breaker);
        $request = new WP_REST_Request();
        $request->set_body(json_encode(['from'=>'2024-01-01','to'=>'2024-01-02']));
        $resp = $controller->handle($request);
        $this->assertInstanceOf(WP_Error::class, $resp);
        $this->assertSame(500, $resp->get_error_data()['status']);
        $this->assertSame(1, $collector->all()['counters']['exports_failed']);
    }
}

<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Http\Rest\ExportController;
use SmartAlloc\Infra\Export\ExporterService;
use SmartAlloc\Tests\BaseTestCase;

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
        public function get_error_data(): array { return $this->data; }
    }
}
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private string $body = '';
        public function set_body(string $b): void { $this->body = $b; }
        public function get_json_params(): array { return json_decode($this->body, true) ?: []; }
    }
}
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct(private array $data = [], private int $status = 200) {}
        public function get_data(): array { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

final class ExportControllerTest extends BaseTestCase
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

    public function test_export_requires_capability(): void
    {
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(false);
        $service = new class extends ExporterService { public function __construct() {} };
        $controller = new ExportController($service);
        $request = new class([]) extends WP_REST_Request {
            public function __construct(private array $p) {}
            public function get_json_params(): array { return $this->p; }
        };
        $response = $controller->handle($request);
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame(403, $response->get_error_data()['status']);
    }

    public function test_export_returns_payload(): void
    {
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\when('wp_checkdate')->alias(fn($m,$d,$y,$date) => checkdate($m,$d,$y));

        $service = new class extends ExporterService {
            public function __construct() {}
            public function generate(string $from, string $to, ?int $batch = null): array {
                return ['file' => '/tmp/file.xlsx', 'url' => 'http://example.com/file.xlsx', 'rows_exported' => 10];
            }
        };
        $controller = new ExportController($service);
        $request = new class(['from' => '2024-01-01', 'to' => '2024-01-02']) extends WP_REST_Request {
            public function __construct(private array $p) {}
            public function get_json_params(): array { return $this->p; }
        };
        $response = $controller->handle($request);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('http://example.com/file.xlsx', $data['url']);
        $this->assertSame(10, $data['rows_exported']);
    }
}

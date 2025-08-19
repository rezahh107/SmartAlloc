<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Http\Rest\HealthController;

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
        public function get_error_data(): array { return $this->data; }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private array $params = [];
        public function get_params(): array { return $this->params; }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct(private array $data = [], private int $status = 200) {}
        public function get_data(): array { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

final class HealthEndpointTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function prepare($q) { return $q; }
            public function get_var($q) { return 1; }
        };
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_health_returns_ok_and_booleans(): void
    {
        $GLOBALS['sa_options']['smartalloc_version'] = '1.2.3';
        $GLOBALS['sa_options']['smartalloc_last_migration'] = '2025-08-18T09:00:00Z';

        $controller = new HealthController();
        $response   = $controller->handle(new WP_REST_Request());
        $data       = $response->get_data();

        $this->assertTrue($data['ok']);
        $this->assertTrue($data['db']);
        $this->assertTrue($data['cache']);
        $this->assertSame('1.2.3', $data['version']);
        $this->assertSame('2025-08-18T09:00:00Z', $data['last_migration']);
        $this->assertArrayHasKey('request', $data['notes']);
        $this->assertSame(8, strlen($data['notes']['request']));
    }
}

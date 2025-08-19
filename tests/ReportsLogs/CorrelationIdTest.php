<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ReportsLogs;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Infra\Logging\Logger;
use SmartAlloc\Http\Rest\HealthController;
use SmartAlloc\Tests\BaseTestCase;

final class CorrelationIdTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Monkey::class)) {
            self::markTestSkipped('Brain Monkey not installed');
        }
        Monkey\setUp();
        Functions\when('get_option')->alias(fn() => '1.0.0');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_id_propagates_and_hashes(): void
    {
        $ref = new \ReflectionClass(Logger::class);
        $prop = $ref->getProperty('requestId');
        $prop->setAccessible(true);
        $prop->setValue(null, 'deadbeefdeadbeef');

        $logger = new Logger();
        $logger->info('hello');
        $record = $logger->records[0];
        $this->assertSame('deadbeefdeadbeef', $record['correlation_id']);

        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function get_var($sql) { return 1; }
            public function prepare($sql, $v = []) { return $sql; }
        };

        $controller = new HealthController();
        $resp = $controller->handle(new \WP_REST_Request());
        $data = $resp->get_data();
        $expected = substr(hash('sha256', 'deadbeefdeadbeef'), 0, 8);
        $this->assertSame($expected, $data['notes']['request']);
    }
}

<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Health;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Health\HealthReporter;
use SmartAlloc\Tests\BaseTestCase;

final class HealthReporterTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $GLOBALS['t'] = [];

        Functions\when('SmartAlloc\\Health\\wp_create_nonce')->alias(fn(string $action): string => 'nonce');
        Functions\when('SmartAlloc\\Health\\admin_url')->alias(fn(string $path = ''): string => 'https://example.com/' . ltrim($path, '/'));
        Functions\when('SmartAlloc\\Services\\apply_filters')->alias(fn(string $hook, $value, ...$args) => $value);
        Functions\when('SmartAlloc\\Services\\get_transient')->alias(fn(string $key) => $GLOBALS['t'][$key] ?? false);
        Functions\when('SmartAlloc\\Services\\set_transient')->alias(function (string $key, $value, int $ttl): bool { $GLOBALS['t'][$key] = $value; return true; });
        Functions\when('SmartAlloc\\Services\\wp_date')->alias(fn($format) => time());
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testHealthyStatusForClosedCircuit(): void
    {
        $reporter = new HealthReporter();
        $health   = $reporter->get_circuit_breaker_health('test');

        $this->assertEquals('circuit_breaker', $health['component']);
        $this->assertEquals('healthy', $health['status']);
        $this->assertArrayHasKey('details', $health);
        $this->assertArrayHasKey('timestamp', $health);
    }

    public function testResponseFormatValidation(): void
    {
        $reporter = new HealthReporter();
        $health   = $reporter->get_circuit_breaker_health('test');

        $this->assertArrayHasKey('component', $health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('details', $health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $health['timestamp']);
    }

    public function testStaticHelperMethods(): void
    {
        $nonce    = HealthReporter::get_nonce();
        $ajax_url = HealthReporter::get_ajax_url();

        $this->assertIsString($nonce);
        $this->assertStringContainsString('admin-ajax.php', $ajax_url);
    }
}

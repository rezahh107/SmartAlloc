<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Health;

use SmartAlloc\Health\HealthReporter;
use SmartAlloc\Tests\Unit\TestCase;
use Brain\Monkey;

final class HealthReporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupWordPressMocks();
    }

    public function testAjaxHealthCheckWithValidNonce(): void
    {
        $_POST['nonce'] = 'test_nonce_' . md5('smartalloc_health_check' . 'salt');
        $_POST['circuit_key'] = 'test';

        $reporter = new HealthReporter();

        // Expect wp_send_json_success to be called
        $this->expectOutputRegex('/{"success":true/');

        $reporter->ajax_health_check();
    }

    public function testAjaxHealthCheckWithInvalidNonce(): void
    {
        $_POST['nonce'] = 'invalid_nonce';

        $reporter = new HealthReporter();

        // Expect wp_send_json_error to be called
        $this->expectOutputRegex('/{"success":false/');

        $reporter->ajax_health_check();
    }

    public function testHealthResponseFormat(): void
    {
        $reporter = new HealthReporter();
        $health = $reporter->get_circuit_breaker_health('test');

        $this->assertArrayHasKey('component', $health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('details', $health);
        $this->assertArrayHasKey('timestamp', $health);

        $this->assertEquals('circuit_breaker', $health['component']);
        $this->assertContains($health['status'], ['healthy', 'degraded']);
    }
}


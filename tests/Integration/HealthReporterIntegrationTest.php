<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Health\HealthReporter;
use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\ValueObjects\CircuitBreakerStatus;
use SmartAlloc\Tests\BaseTestCase;

final class HealthReporterIntegrationTest extends BaseTestCase
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

    public function testAjaxHealthCheckWithValidNonce(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('wp_send_json_success')->alias(fn($d) => print json_encode(['success'=>true,'data'=>$d]));
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);
        $_POST['_wpnonce'] = 'ok';

        $status  = new CircuitBreakerStatus('closed',0,5,null,null);
        $breaker = $this->createMock(CircuitBreaker::class);
        $breaker->method('getStatus')->willReturn($status);

        $reporter = new HealthReporter($breaker);
        $this->expectOutputRegex('/"success":true/');
        $reporter->handle_health_check();
    }

    public function testAjaxHealthCheckWithInvalidNonce(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(false);
        Functions\when('wp_send_json_error')->alias(fn($d,$c)=>(print json_encode(['success'=>false,'data'=>$d])));
        $_POST['_wpnonce'] = 'bad';

        $breaker  = $this->createMock(CircuitBreaker::class);
        $reporter = new HealthReporter($breaker);
        $this->expectOutputRegex('/"success":false/');
        $reporter->handle_health_check();
    }
}

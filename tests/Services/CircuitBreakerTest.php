<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Services;

use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Services\CircuitBreakerStatus;
use SmartAlloc\Services\Exceptions\CircuitOpenException;
use WP_UnitTestCase;

final class CircuitBreakerTest extends WP_UnitTestCase
{
    private const TEST_CIRCUIT_KEY = 'test_circuit';
    private const TRANSIENT_KEY = 'smartalloc_circuit_breaker_' . self::TEST_CIRCUIT_KEY;

    protected function setUp(): void
    {
        parent::setUp();
        delete_transient(self::TRANSIENT_KEY);
        delete_transient('smartalloc_circuit_breaker_default');
        remove_all_filters('smartalloc_cb_threshold');
        remove_all_filters('smartalloc_cb_cooldown');
    }

    protected function tearDown(): void
    {
        delete_transient(self::TRANSIENT_KEY);
        delete_transient('smartalloc_circuit_breaker_default');
        remove_all_filters('smartalloc_cb_threshold');
        remove_all_filters('smartalloc_cb_cooldown');
        parent::tearDown();
    }

    public function test_dto_state_helpers_for_closed_circuit(): void
    {
        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $status = $cb->getStatus();

        $this->assertTrue($status->isClosed());
        $this->assertFalse($status->isOpen());
        $this->assertFalse($status->isHalfOpen());
        $this->assertSame('closed', $status->state);
    }

    public function test_dto_state_helpers_for_open_circuit(): void
    {
        set_transient(self::TRANSIENT_KEY, [
            'state' => 'open',
            'fail_count' => 5,
            'cooldown_until' => time() + 300,
            'last_error' => 'Test error',
        ], 3600);

        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $status = $cb->getStatus();

        $this->assertTrue($status->isOpen());
        $this->assertFalse($status->isClosed());
        $this->assertFalse($status->isHalfOpen());
        $this->assertSame('open', $status->state);
    }

    public function test_dto_state_helpers_for_half_open_circuit(): void
    {
        set_transient(self::TRANSIENT_KEY, [
            'state' => 'half-open',
            'fail_count' => 0,
            'cooldown_until' => null,
            'last_error' => null,
        ], 3600);

        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $status = $cb->getStatus();

        $this->assertTrue($status->isHalfOpen());
        $this->assertFalse($status->isClosed());
        $this->assertFalse($status->isOpen());
        $this->assertSame('half-open', $status->state);
    }

    public function test_filtered_threshold_applied(): void
    {
        $custom_threshold = 10;
        add_filter('smartalloc_cb_threshold', function ($default, $key) use ($custom_threshold) {
            return $key === self::TEST_CIRCUIT_KEY ? $custom_threshold : $default;
        }, 10, 2);

        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $status = $cb->getStatus();

        $this->assertSame($custom_threshold, $status->threshold);
    }

    public function test_filtered_cooldown_applied(): void
    {
        $custom_cooldown = 600;
        add_filter('smartalloc_cb_cooldown', function ($default, $key) use ($custom_cooldown) {
            return $key === self::TEST_CIRCUIT_KEY ? $custom_cooldown : $default;
        }, 10, 2);

        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);

        for ($i = 0; $i < 5; $i++) {
            try {
                $cb->recordFailure('Test error');
            } catch (CircuitOpenException $e) {
            }
        }

        $status = $cb->getStatus();
        $expected = time() + $custom_cooldown;
        $this->assertEqualsWithDelta($expected, $status->cooldownUntil, 2);
    }

    public function test_multiple_circuit_keys_use_different_filters(): void
    {
        $key1 = 'circuit_1';
        $key2 = 'circuit_2';

        add_filter('smartalloc_cb_threshold', function ($default, $key) {
            return $key === 'circuit_1' ? 3 : 7;
        }, 10, 2);

        $cb1 = new CircuitBreaker($key1);
        $cb2 = new CircuitBreaker($key2);

        $this->assertSame(3, $cb1->getStatus()->threshold);
        $this->assertSame(7, $cb2->getStatus()->threshold);
    }

    public function test_auto_recovery_when_cooldown_expired(): void
    {
        $expired = time() - 100;
        set_transient(self::TRANSIENT_KEY, [
            'state' => 'open',
            'fail_count' => 5,
            'cooldown_until' => $expired,
            'last_error' => 'Previous error',
        ], 3600);

        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $status = $cb->getStatus();

        $this->assertTrue($status->isHalfOpen());
        $this->assertSame(0, $status->failCount);
        $this->assertNull($status->cooldownUntil);

        $transient = get_transient(self::TRANSIENT_KEY);
        $this->assertSame('half-open', $transient['state']);
        $this->assertSame(0, $transient['fail_count']);
    }

    public function test_no_auto_recovery_when_cooldown_not_expired(): void
    {
        $future = time() + 300;
        set_transient(self::TRANSIENT_KEY, [
            'state' => 'open',
            'fail_count' => 5,
            'cooldown_until' => $future,
            'last_error' => 'Recent error',
        ], 3600);

        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $status = $cb->getStatus();

        $this->assertTrue($status->isOpen());
        $this->assertSame(5, $status->failCount);
        $this->assertSame($future, $status->cooldownUntil);
    }

    public function test_auto_recovery_preserves_last_error(): void
    {
        $expired = time() - 100;
        $original = 'Original error message';
        set_transient(self::TRANSIENT_KEY, [
            'state' => 'open',
            'fail_count' => 5,
            'cooldown_until' => $expired,
            'last_error' => $original,
        ], 3600);

        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $status = $cb->getStatus();

        $this->assertTrue($status->isHalfOpen());
        $this->assertSame($original, $status->lastError);
    }

    public function test_transient_initialization_when_missing(): void
    {
        delete_transient(self::TRANSIENT_KEY);

        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $status = $cb->getStatus();

        $this->assertTrue($status->isClosed());
        $this->assertSame(0, $status->failCount);
        $this->assertNull($status->cooldownUntil);
        $this->assertNull($status->lastError);
    }

    public function test_transient_persistence_after_failure(): void
    {
        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $cb->recordFailure('Test error');

        $data = get_transient(self::TRANSIENT_KEY);
        $this->assertIsArray($data);
        $this->assertSame('closed', $data['state']);
        $this->assertSame(1, $data['fail_count']);
        $this->assertSame('Test error', $data['last_error']);
    }

    public function test_transient_cleanup_after_success(): void
    {
        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $cb->recordFailure('Test error');
        $cb->recordSuccess();

        $data = get_transient(self::TRANSIENT_KEY);
        $this->assertSame('closed', $data['state']);
        $this->assertSame(0, $data['fail_count']);
        $this->assertNull($data['cooldown_until']);
    }

    public function test_circuit_open_exception_thrown_at_threshold(): void
    {
        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);

        for ($i = 0; $i < 4; $i++) {
            $cb->recordFailure('Test error ' . $i);
        }

        $this->expectException(CircuitOpenException::class);
        $this->expectExceptionMessage('Circuit breaker opened due to failure threshold exceeded');

        $cb->recordFailure('Final error');
    }

    public function test_circuit_open_exception_contains_metadata(): void
    {
        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);

        try {
            for ($i = 0; $i < 5; $i++) {
                $cb->recordFailure('Test error');
            }
        } catch (CircuitOpenException $e) {
            $this->assertStringContainsString(self::TEST_CIRCUIT_KEY, $e->getCircuitKey());
            $this->assertSame(5, $e->getFailureCount());
            $this->assertGreaterThan(time(), $e->getCooldownUntil());
        }
    }

    public function test_error_message_sanitization(): void
    {
        $long = str_repeat('A', 150);
        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
        $cb->recordFailure($long);

        $status = $cb->getStatus();
        $this->assertSame(100, strlen($status->lastError));
        $this->assertSame(str_repeat('A', 100), $status->lastError);
    }

    public function test_guard_uses_utc_time_across_timezones(): void
    {
        $timezones = ['UTC', 'America/New_York', 'Asia/Tokyo', 'Europe/London'];

        foreach ($timezones as $tz) {
            update_option('timezone_string', $tz);
            delete_transient(self::TRANSIENT_KEY);

            $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);
            for ($i = 0; $i < 5; $i++) {
                try {
                    $cb->recordFailure('fail');
                } catch (CircuitOpenException $e) {
                }
            }

            $status = $cb->getStatus();
            $expected = (int) wp_date('U') + 300;
            $this->assertEqualsWithDelta($expected, $status->cooldownUntil, 5);

            delete_transient(self::TRANSIENT_KEY);
        }

        update_option('timezone_string', 'UTC');
    }

    public function test_cooldown_calculation_utc_consistency(): void
    {
        delete_transient(self::TRANSIENT_KEY);
        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);

        $start = (int) wp_date('U');
        for ($i = 0; $i < 5; $i++) {
            try {
                $cb->recordFailure('fail');
            } catch (CircuitOpenException $e) {
            }
        }

        $state = get_transient(self::TRANSIENT_KEY);
        $this->assertIsArray($state);
        $this->assertGreaterThanOrEqual($start + 300, $state['cooldown_until']);
        $this->assertLessThanOrEqual($start + 305, $state['cooldown_until']);
    }

    public function test_failure_method_sanitizes_exception_messages(): void
    {
        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);

        $long_message = str_repeat('X', 150);
        $exception = new \Exception($long_message);

        $cb->failure('test_context', $exception);

        $status = $cb->getStatus();
        $this->assertSame(100, strlen($status->lastError));
        $this->assertSame(str_repeat('X', 100), $status->lastError);
    }

    public function test_failure_method_preserves_short_messages(): void
    {
        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);

        $short_message = 'Short error';
        $exception = new \Exception($short_message);

        $cb->failure('test_context', $exception);

        $status = $cb->getStatus();
        $this->assertSame($short_message, $status->lastError);
    }

    public function test_failure_method_handles_empty_messages(): void
    {
        $cb = new CircuitBreaker(self::TEST_CIRCUIT_KEY);

        $exception = new \Exception('');
        $cb->failure('test_context', $exception);

        $status = $cb->getStatus();
        $this->assertSame('', $status->lastError);
    }

    public function test_failure_and_record_failure_sanitization_consistency(): void
    {
        $cb1 = new CircuitBreaker('circuit_1');
        $cb2 = new CircuitBreaker('circuit_2');

        $long_message = str_repeat('Y', 150);

        $exception = new \Exception($long_message);
        $cb1->failure('context', $exception);

        $cb2->recordFailure($long_message);

        $status1 = $cb1->getStatus();
        $status2 = $cb2->getStatus();

        $this->assertSame($status1->lastError, $status2->lastError);
        $this->assertSame(str_repeat('Y', 100), $status1->lastError);
    }
}

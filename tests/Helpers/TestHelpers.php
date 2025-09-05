<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Tests\Helpers;

use SmartAlloc\Services\Metrics;
use SmartAlloc\CircuitBreaker\CircuitBreakerStatus;

/**
 * Shared test helper classes.
 */
class SpyMetrics extends Metrics {
/**
 * Recorded metric calls.
 *
 * @var array<int, array<string, mixed>>
 */
public array $calls = [];

public function __construct() {}

public function inc(string $key, float $value = 1.0, array $labels = []): void {
global $wpdb;
if (isset($wpdb) && is_object($wpdb)) {
$wpdb->records[] = ['metric_key' => $key];
}
$this->calls[] = ['method' => 'inc', 'key' => $key, 'value' => $value, 'labels' => $labels];
}

public function observe(string $key, int $milliseconds, array $labels = []): void {
$this->inc($key, (float) $milliseconds, $labels);
}

/**
 * Retrieve recorded calls.
 *
 * @return array<int, array<string, mixed>>
 */
public function get_calls(): array {
return $this->calls;
}

public function get_call_count(?string $method = null): int {
if (null === $method) {
return count($this->calls);
}
return count(array_filter($this->calls, fn($c) => $c['method'] === $method));
}

public function clear(): void {
$this->calls = [];
}
}

class MockCircuitBreaker {
private string $state = 'closed';
private int $failure_count = 0;

public function get_status(): CircuitBreakerStatus {
return new CircuitBreakerStatus($this->state, $this->failure_count, null, null);
}

public function set_state(string $state): void {
$this->state = $state;
}

public function set_failure_count(int $count): void {
$this->failure_count = $count;
}
}

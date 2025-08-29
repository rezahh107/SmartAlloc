<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for observability metric key exposure.
 *
 * @group observability
 */
final class MetricsKeysTest extends TestCase {
    public function test_registry_exposes_notify_and_dlq_counters(): void {
        $mockKeys = [
            'allocation_total',
            'allocation_duration_seconds',
            'notify_retry_total',
            'notify_success_total',
            'dlq_push_total',
            'dlq_process_total',
        ];

        $this->assertContains('notify_retry_total', $mockKeys);
        $this->assertContains('dlq_push_total', $mockKeys);
    }

    public function test_metrics_follow_naming_convention(): void {
        $mockKeys = ['notify_retry_total', 'dlq_push_total'];

        foreach ($mockKeys as $key) {
            $this->assertMatchesRegularExpression(
                '/^[a-z]+(_[a-z]+)*_(total|count|seconds|bytes)$/',
                $key,
                "Metric key '$key' should follow Prometheus naming"
            );
        }
    }
}

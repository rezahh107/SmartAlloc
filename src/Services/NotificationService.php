<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Testing\FaultFlags;

/**
 * Notification queue with circuit breaker, retries and DLQ.
 */
final class NotificationService
{
    private RetryService $retry;
    private DlqService $dlq;

    public function __construct(
        private CircuitBreaker $circuitBreaker,
        private Logging $logger,
        private Metrics $metrics,
        ?RetryService $retry = null,
        ?DlqService $dlq = null
    ) {
        $this->retry = $retry ?? new RetryService();
        $this->dlq = $dlq ?? new DlqService();
        add_action('smartalloc_notify', [$this, 'handle']);
    }

    /**
     * Queue notification job.
     *
     * @param array<string,mixed> $payload { event_name, body, _attempt? }
     */
    public function send(array $payload): void
    {
        $payload['_attempt'] = (int) ($payload['_attempt'] ?? 1);
        $this->enqueue($payload, 0);
    }

    /**
     * Process queued job.
     *
     * @param array<string,mixed> $payload
     */
    public function handle(array $payload): void
    {
        $attempt = (int) ($payload['_attempt'] ?? 1);
        $body = $payload['body'] ?? [];
        try {
            $delay = 0;
            if (function_exists('apply_filters')) {
                $delay = (int) apply_filters('smartalloc_test_fault_latency_ms', 0);
                if (isset($GLOBALS['filters']['smartalloc_test_fault_latency_ms'])) {
                    $delay = (int) $GLOBALS['filters']['smartalloc_test_fault_latency_ms']($delay);
                }
            }
            if ($delay > 0) {
                usleep($delay * 1000);
            }
            $ff = FaultFlags::get();
            if (!empty($ff['notify_partial_fail'])) {
                $h = crc32(json_encode($payload));
                if (($h % 10) < 3) {
                    throw new \RuntimeException('Notify failed (test)');
                }
            }
            $this->circuitBreaker->guard('notify');
            $result = true;
            if (function_exists('apply_filters')) {
                $result = apply_filters('smartalloc_notify_transport', true, $body, $attempt);
            }
            if ($result === true && isset($GLOBALS['filters']['smartalloc_notify_transport'])) {
                $result = $GLOBALS['filters']['smartalloc_notify_transport'](true, $body, $attempt);
            }
            if ($result !== true) {
                throw new \RuntimeException(is_string($result) ? $result : 'notify failed');
            }
            $this->circuitBreaker->success('notify');
            $this->metrics->inc('notify_success_total');
            $this->logger->info('notify.success', ['payload' => $payload]);
        } catch (\Throwable $e) {
            $this->circuitBreaker->failure('notify');
            $this->metrics->inc('notify_failed_total');
            $this->logger->warning('notify.fail', ['error' => $e->getMessage(), 'attempt' => $attempt]);
            if ($attempt <= 5) {
                $this->metrics->inc('notify_retry_total');
                $payload['_attempt'] = $attempt + 1;
                $delay = $this->retry->backoff($attempt);
                $this->enqueue($payload, $delay);
                return;
            }
            $this->dlq->push([
                'event_name' => (string) ($payload['event_name'] ?? 'notify'),
                'payload'    => $body,
                'attempts'   => $attempt,
                'error_text' => $e->getMessage(),
            ]);
            $this->metrics->inc('dlq_push_total');
        }
    }

    /**
     * Enqueue action via Action Scheduler or wp-cron.
     *
     * @param array<string,mixed> $payload
     */
    private function enqueue(array $payload, int $delay): void
    {
        $hook = 'smartalloc_notify';
        if (function_exists('as_enqueue_async_action') && function_exists('as_enqueue_single_action')) {
            $group = 'smartalloc';
            if ($delay > 0) {
                as_enqueue_single_action(time() + $delay, $hook, [$payload], $group, true);
            } else {
                as_enqueue_async_action($hook, [$payload], $group, true);
            }
            return;
        }
        wp_schedule_single_event(time() + $delay, $hook, [$payload]);
    }
}

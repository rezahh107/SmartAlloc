<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\Metrics;

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
     * @param array<string,mixed> $payload
     */
    public function send(array $payload): void
    {
        $this->enqueue($payload, 1, 0);
    }

    /**
     * Process queued job.
     *
     * @param array<string,mixed> $args
     */
    public function handle(array $args): void
    {
        $payload = $args['payload'] ?? [];
        $attempt = (int) ($args['_attempt'] ?? 1);
        try {
            $this->circuitBreaker->guard('notify');
            $result = apply_filters('smartalloc_notify_transport', true, $payload, $attempt);
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
            if ($attempt < 5) {
                $this->metrics->inc('notify_retry_total');
                $delay = $this->retry->backoff($attempt);
                $this->enqueue($payload, $attempt + 1, $delay);
                return;
            }
            $this->dlq->push((string) ($payload['event_name'] ?? 'notify'), $payload, $e->getMessage(), $attempt);
            $this->metrics->inc('dlq_push_total');
        }
    }

    /**
     * Enqueue action via Action Scheduler or wp-cron.
     *
     * @param array<string,mixed> $payload
     */
    private function enqueue(array $payload, int $attempt, int $delay): void
    {
        $args = ['payload' => $payload, '_attempt' => $attempt];
        $hook = 'smartalloc_notify';
        if (function_exists('as_enqueue_async_action')) {
            $group = 'smartalloc';
            if ($delay > 0) {
                as_enqueue_single_action(time() + $delay, $hook, [$args], $group, true);
            } else {
                as_enqueue_async_action($hook, [$args], $group, true);
            }
            return;
        }
        wp_schedule_single_event(time() + $delay, $hook, [$args]);
    }
}

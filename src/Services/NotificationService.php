<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\Metrics;
use WP_Error;

/**
 * Notification queue with circuit breaker, retries and DLQ.
 */
final class NotificationService
{
    private string $dlqTable;

    public function __construct(
        private CircuitBreaker $circuitBreaker,
        private Logging $logger,
        private Metrics $metrics
    ) {
        global $wpdb;
        $this->dlqTable = $wpdb->prefix . 'salloc_dlq';
        add_action('smartalloc_notify', [$this, 'handle'], 10, 2);
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
     * @param array<string,mixed> $payload
     */
    public function handle(array $payload, int $attempt = 1): void
    {
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
                $delay = (int) pow(2, $attempt) + rand(0, 3);
                $this->enqueue($payload, $attempt + 1, $delay);
                return;
            }
            $this->pushDlq($payload, $e->getMessage(), $attempt);
        }
    }

    /**
     * Enqueue action via Action Scheduler or wp-cron.
     *
     * @param array<string,mixed> $payload
     */
    private function enqueue(array $payload, int $attempt, int $delay): void
    {
        $args = ['payload' => $payload, 'attempt' => $attempt];
        $hook = 'smartalloc_notify';
        if (function_exists('as_enqueue_async_action')) {
            $group = 'smartalloc';
            if (false === as_next_scheduled_action($hook, $args, $group)) {
                if ($delay > 0) {
                    as_enqueue_single_action(time() + $delay, $hook, $args, $group, true);
                } else {
                    as_enqueue_async_action($hook, $args, $group, true);
                }
            }
            return;
        }
        if (false === wp_next_scheduled($hook, $args)) {
            wp_schedule_single_event(time() + $delay, $hook, $args);
        }
    }

    /**
     * Push payload to DLQ.
     *
     * @param array<string,mixed> $payload
     */
    private function pushDlq(array $payload, string $error, int $attempt): void
    {
        global $wpdb;
        $this->metrics->inc('dlq_push_total');
        $wpdb->insert($this->dlqTable, [
            'payload_json'  => wp_json_encode($payload),
            'last_error'    => $error,
            'attempts'      => $attempt,
            'created_at_utc'=> gmdate('Y-m-d H:i:s'),
            'status'        => 'ready',
        ]);
    }
}

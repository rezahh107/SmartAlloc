<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Support\CircuitBreaker as SafetyCircuitBreaker;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\Metrics;
use SmartAlloc\ValueObjects\ThrottleConfig;
use SmartAlloc\Testing\FaultFlags;
use SmartAlloc\Observability\Tracer;

/**
 * Notification queue with circuit breaker, retries and DLQ.
 */
final class NotificationService
{
    private const RATE_LIMIT_KEY = 'smartalloc_notify_rate';
    private RetryService $retry;
    private DlqService $dlq;
    private ThrottleConfig $throttleConfig;
    private NotificationThrottler $throttler;
    private DLQMetrics $dlqMetrics;

    public function __construct(
        private CircuitBreaker $circuitBreaker,
        private Logging $logger,
        private Metrics $metrics,
        ?RetryService $retry = null,
        ?DlqService $dlq = null,
        ?ThrottleConfig $config = null
    ) {
        $this->retry = $retry ?? new RetryService();
        $this->dlq = $dlq ?? new DlqService();
        $this->throttler = new NotificationThrottler();
        $this->dlqMetrics = new DLQMetrics();
        $cfg = $this->getThrottleConfig();
        $this->throttleConfig = $config ?? new ThrottleConfig(
            (int) $cfg['limit_per_minute'],
            (int) $cfg['burst_limit'],
            (int) $cfg['window_seconds']
        );
        add_action('smartalloc_notify', [$this, 'handle']);
    }

    /**
     * Queue notification job.
     *
     * @param array<string,mixed> $payload { event_name, body, _attempt? }
     */
    public function send(array $payload): void
    {
        if (!$this->checkRateLimit()) {
            $this->logger->warning('notify.throttle');
            return;
        }
        $recipient = (string) ($payload['recipient'] ?? '');
        if (!$this->throttler->canSend($recipient)) {
            $this->dlqMetrics->recordPush('notification_throttled', ['recipient' => $recipient]);
            $this->dlq->push([
                'event_name' => 'notify',
                'payload'    => $payload,
                'attempts'   => 1,
                'error_text' => 'rate_limit',
            ]);
            $this->metrics->inc('notify_throttled_total');
            throw new Exceptions\ThrottleException('Rate limit exceeded');
        }
        $payload['_attempt'] = (int) ($payload['_attempt'] ?? 1);
        $this->incrementRateCounter();
        $this->throttler->recordSend($recipient);
        $this->enqueue('smartalloc_notify', $payload, 0);
    }

    /**
     * Process queued job.
     *
     * @param array<string,mixed> $payload
     */
    public function handle(array $payload): void
    {
        $attempt = (int) ($payload['_attempt'] ?? 1);
        if (defined('SMARTALLOC_TEST_MODE') && SMARTALLOC_TEST_MODE) {
            Tracer::start('notify.dispatch');
        }
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
                $h = crc32(wp_json_encode($payload));
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
            $this->circuitBreaker->failure('notify',$e);
            $this->metrics->inc('notify_failed_total');
            $this->logger->warning('notify.fail', ['error' => $e->getMessage(), 'attempt' => $attempt]);
            $maxTries = defined('SMARTALLOC_NOTIFY_MAX_TRIES') ? (int) SMARTALLOC_NOTIFY_MAX_TRIES : 5;
            if ($attempt < $maxTries) {
                $this->metrics->inc('notify_retry_total');
                $payload['_attempt'] = $attempt + 1;
                $delay = $this->retry->backoff($attempt);
                $this->enqueue('smartalloc_notify', $payload, $delay);
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
        if (defined('SMARTALLOC_TEST_MODE') && SMARTALLOC_TEST_MODE) {
            Tracer::finish('notify.dispatch');
        }
    }

    /**
     * Send mail with retry and idempotency.
     *
     * @param array<string,mixed> $payload { to,subject,message,headers?,_attempt? }
     */
    public function sendMail(array $payload): bool
    {
        $attempt    = (int) ( $payload['_attempt'] ?? 1 );
        $id_payload = $payload;
        unset( $id_payload['_attempt'] );
        $key = 'sa_mail_' . sha1( wp_json_encode( $id_payload ) );
        if ( get_transient( $key ) ) {
            return true;
        }
        try {
            $ok = SafetyCircuitBreaker::call(
                'mail',
                fn() => wp_mail( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
                    (string) ( $payload['to'] ?? '' ),
                    (string) ( $payload['subject'] ?? '' ),
                    (string) ( $payload['message'] ?? '' ),
                    $payload['headers'] ?? array()
                ),
                5,
                120
            );
            $err = '';
        } catch (\Throwable $e) {
            $ok  = false;
            $err = $e->getMessage();
        }
        if ( $ok ) {
            set_transient( $key, 1, DAY_IN_SECONDS );
            return true;
        }
        $this->logger->error( 'notify.mail.fail', array( 'email' => $payload['to'] ?? '', 'attempt' => $attempt, 'error' => $err ) );
        if ( $attempt >= SMARTALLOC_NOTIFY_MAX_TRIES ) {
            $this->dlq->push(array(
                'event_name' => 'mail',
                'payload'    => $payload,
                'attempts'   => $attempt,
                'error_text' => $err,
            ));
            return false;
        }
        $payload['_attempt'] = $attempt + 1;
        $this->enqueue( 'smartalloc_notify_mail', $payload, $this->mailDelay( $attempt ) );
        return false;
    }

    /**
     * Calculate exponential backoff with jitter.
     */
    private function mailDelay( int $attempt ): int {
        $base = SMARTALLOC_NOTIFY_BASE_DELAY;
        $cap  = SMARTALLOC_NOTIFY_BACKOFF_CAP;
        $j    = random_int( 0, $base );
        return (int) min( $cap, $base * ( 2 ** ( $attempt - 1 ) ) + $j );
    }

    /**
     * Enqueue action via Action Scheduler or wp-cron.
     *
     * @param array<string,mixed> $payload
     */
    private function enqueue( string $hook, array $payload, int $delay ): void
    {
        if (function_exists('as_enqueue_async_action') && function_exists('as_enqueue_single_action')) {
            $group = 'smartalloc';
            if ($delay > 0) {
                as_enqueue_single_action(time() + $delay, $hook, [$payload], $group, true);
            } else {
                as_enqueue_async_action($hook, [$payload], $group, true);
            }
            return;
        }
        wp_schedule_single_event(time() + max(1, $delay), $hook, [$payload]);
    }

    private function getThrottleConfig(): array
    {
        $defaults = [
            'limit_per_minute' => 10,
            'burst_limit' => 10,
            'window_seconds' => 60,
            'dlq_enabled' => true,
        ];
        $config = get_option('smartalloc_notify', $defaults);
        $config['limit_per_minute'] = function_exists('apply_filters') ? apply_filters('smartalloc_notify_limit_per_min', $config['limit_per_minute']) : $config['limit_per_minute'];
        if (isset($GLOBALS['filters']['smartalloc_notify_limit_per_min'])) {
            $config['limit_per_minute'] = $GLOBALS['filters']['smartalloc_notify_limit_per_min']($config['limit_per_minute']);
        }
        $config['burst_limit'] = function_exists('apply_filters') ? apply_filters('smartalloc_notify_burst', $config['burst_limit']) : $config['burst_limit'];
        if (isset($GLOBALS['filters']['smartalloc_notify_burst'])) {
            $config['burst_limit'] = $GLOBALS['filters']['smartalloc_notify_burst']($config['burst_limit']);
        }
        $config['window_seconds'] = function_exists('apply_filters') ? apply_filters('smartalloc_notify_window', $config['window_seconds']) : $config['window_seconds'];
        if (isset($GLOBALS['filters']['smartalloc_notify_window'])) {
            $config['window_seconds'] = $GLOBALS['filters']['smartalloc_notify_window']($config['window_seconds']);
        }
        return $config;
    }

    private function checkRateLimit(): bool
    {
        $current = (int) (get_transient(self::RATE_LIMIT_KEY) ?: 0);
        return $current < $this->throttleConfig->burstLimit();
    }

    private function incrementRateCounter(): void
    {
        $current = (int) (get_transient(self::RATE_LIMIT_KEY) ?: 0);
        set_transient(self::RATE_LIMIT_KEY, $current + 1, $this->throttleConfig->windowSeconds());
    }
}

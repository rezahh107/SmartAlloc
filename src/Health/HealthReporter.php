<?php

declare(strict_types=1);

namespace SmartAlloc\Health;

use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\ValueObjects\CircuitBreakerStatus;

/**
 * Provides circuit breaker health information via AJAX.
 */
final class HealthReporter
{
    private CircuitBreaker $circuitBreaker;
    private string $nonceAction = 'smartalloc_health_check';
    private int $cacheTtl = 30;

    public function __construct(CircuitBreaker $circuitBreaker)
    {
        $this->circuitBreaker = $circuitBreaker;
    }

    public function register_hooks(): void
    {
        add_action('wp_ajax_smartalloc_health', [$this, 'handle_health_check']);
        add_action('wp_ajax_nopriv_smartalloc_health', [$this, 'handle_health_check']);
    }

    public function handle_health_check(): void
    {
        if (!$this->verify_nonce()) {
            wp_send_json_error([
                'error' => 'Invalid nonce',
                'code'  => 'INVALID_NONCE',
            ], 403);
            return;
        }

        try {
            $health = $this->get_health_status();
            wp_send_json_success($health['data']);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'error'   => 'Health check failed',
                'code'    => 'HEALTH_CHECK_ERROR',
                'message' => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Retrieve health information, cached for a short period.
     *
     * @return array{success:bool,data:array<string,mixed>}
     */
    public function get_health_status(): array
    {
        $cacheKey = 'smartalloc_health_status';
        $cached   = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $status   = $this->circuitBreaker->getStatus();
        $response = [
            'success' => true,
            'data'    => [
                'status'        => $this->map_status($status),
                'circuit_state' => $status->state,
                'failure_count' => $status->failCount,
                'last_failure'  => $status->lastError,
                'next_retry'    => $status->cooldownUntil,
                'timestamp'     => gmdate('c'),
            ],
        ];

        set_transient($cacheKey, $response, $this->cacheTtl);
        return $response;
    }

    private function verify_nonce(): bool
    {
        $nonce = sanitize_text_field($_POST['_wpnonce'] ?? '');
        return wp_verify_nonce($nonce, $this->nonceAction) === 1;
    }

    private function map_status(CircuitBreakerStatus $status): string
    {
        return match ($status->state) {
            'closed'    => 'healthy',
            'half-open' => 'degraded',
            'open'      => 'down',
            default     => 'unknown',
        };
    }

    public function get_nonce(): string
    {
        return wp_create_nonce($this->nonceAction);
    }
}

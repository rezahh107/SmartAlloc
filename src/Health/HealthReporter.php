<?php

declare(strict_types=1);

namespace SmartAlloc\Health;

use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Services\CircuitBreakerStatus;

/**
 * Reports circuit breaker health via AJAX endpoints.
 */
final class HealthReporter
{
    private const NONCE_ACTION = 'smartalloc_health_check';
    private const AJAX_ACTION  = 'smartalloc_health';

    public function register_hooks(): void
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajax_health_check']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [$this, 'ajax_health_check']);
    }

    public function ajax_health_check(): void
    {
        $nonce = sanitize_key($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(
                [
                    'message'   => 'Invalid security token',
                    'timestamp' => gmdate('c'),
                ],
                403
            );
            return;
        }

        $circuit_key = sanitize_text_field($_POST['circuit_key'] ?? 'default');
        $health_data = $this->get_circuit_breaker_health($circuit_key);

        wp_send_json_success($health_data);
    }

    /**
     * Retrieve health information for a circuit breaker.
     *
     * @param string $circuit_key Circuit identifier.
     *
     * @return array<string,mixed>
     */
    public function get_circuit_breaker_health(string $circuit_key = 'default'): array
    {
        try {
            $circuit_breaker = new CircuitBreaker($circuit_key);
            $status          = $circuit_breaker->getStatus();

            return [
                'component'   => 'circuit_breaker',
                'circuit_key' => $circuit_key,
                'status'      => $this->determine_health_status($status),
                'details'     => $this->format_status_details($status),
                'timestamp'   => gmdate('c'),
            ];
        } catch (\Throwable $e) {
            return [
                'component'   => 'circuit_breaker',
                'circuit_key' => $circuit_key,
                'status'      => 'degraded',
                'details'     => [
                    'error'   => 'Health check failed',
                    'message' => substr($e->getMessage(), 0, 100),
                ],
                'timestamp'   => gmdate('c'),
            ];
        }
    }

    private function determine_health_status(CircuitBreakerStatus $status): string
    {
        if ($status->isOpen()) {
            return 'degraded';
        }

        if ($status->isHalfOpen()) {
            return 'degraded';
        }

        if ($status->failCount >= ($status->threshold * 0.8)) {
            return 'degraded';
        }

        return 'healthy';
    }

    /**
     * Format details section of health response.
     *
     * @return array<string,mixed>
     */
    private function format_status_details(CircuitBreakerStatus $status): array
    {
        $details = [
            'state'        => $status->state,
            'fail_count'   => $status->failCount,
            'threshold'    => $status->threshold,
            'failure_rate' => $status->threshold > 0 ? round(($status->failCount / $status->threshold) * 100, 2) : 0,
        ];

        if ($status->cooldownUntil !== null) {
            $details['cooldown_until']    = gmdate('c', $status->cooldownUntil);
            $details['cooldown_remaining'] = max(0, $status->cooldownUntil - (int) wp_date('U'));
        }

        if ($status->lastError !== null) {
            $details['last_error'] = $status->lastError;
        }

        return $details;
    }

    public static function get_nonce(): string
    {
        return wp_create_nonce(self::NONCE_ACTION);
    }

    public static function get_ajax_url(): string
    {
        return admin_url('admin-ajax.php');
    }
}

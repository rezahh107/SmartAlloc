<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

final class CircuitBreaker
{
    private const DEFAULT_THRESHOLD = 5;
    private const DEFAULT_COOLDOWN = 300; // 5 minutes
    private const TRANSIENT_KEY = 'smartalloc_circuit_breaker';

    private int $threshold;
    private int $cooldown;
    private string $transientKey;

    public function __construct(string $key = 'default')
    {
        $this->threshold = (int) apply_filters('smartalloc_cb_threshold', self::DEFAULT_THRESHOLD, $key);
        $this->cooldown = (int) apply_filters('smartalloc_cb_cooldown', self::DEFAULT_COOLDOWN, $key);
        $this->transientKey = self::TRANSIENT_KEY . '_' . $key;
    }

    public function getStatus(): CircuitBreakerStatus
    {
        $data = get_transient($this->transientKey);

        if ($data === false) {
            return new CircuitBreakerStatus(
                'closed',
                0,
                $this->threshold
            );
        }

        return new CircuitBreakerStatus(
            $data['state'] ?? 'closed',
            $data['fail_count'] ?? 0,
            $this->threshold,
            $data['cooldown_until'] ?? null,
            $data['last_error'] ?? null
        );
    }

    public function recordFailure(string $error): void
    {
        $status = $this->getStatus();
        $newFailCount = $status->failCount + 1;

        $newState = $newFailCount >= $this->threshold ? 'open' : 'closed';
        $cooldownUntil = $newState === 'open' ? wp_date('U') + $this->cooldown : null;

        $this->saveState($newState, $newFailCount, $cooldownUntil, $error);
    }

    public function recordSuccess(): void
    {
        $this->saveState('closed', 0, null, null);
    }

    public function guard(string $context): void
    {
        $status = $this->getStatus();

        if (
            $status->state === 'open' &&
            ($status->cooldownUntil === null || $status->cooldownUntil > (int) wp_date('U'))
        ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \RuntimeException('Circuit breaker open for ' . $context);
        }
    }

    public function success(string $context): void
    {
        unset($context);
        $this->recordSuccess();
    }

    public function failure(string $context, \Throwable $exception): void
    {
        unset($context);
        $this->recordFailure($exception->getMessage());
    }

    private function saveState(
        string $state,
        int $failCount,
        ?int $cooldownUntil,
        ?string $lastError
    ): void {
        $data = [
            'state' => $state,
            'fail_count' => $failCount,
            'cooldown_until' => $cooldownUntil,
            'last_error' => $lastError ? substr($lastError, 0, 100) : null,
        ];

        set_transient($this->transientKey, $data, $this->cooldown + 60);
    }
}

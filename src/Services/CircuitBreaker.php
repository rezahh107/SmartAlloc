<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Services\Exceptions\CircuitOpenException;

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
        $data = $this->retrieveOrInitializeTransient();
        $data = $this->autoRecoverIfExpired($data);
        $data = $this->sanitizeErrorMessage($data);

        return new CircuitBreakerStatus(
            $data['state'],
            $data['fail_count'],
            $this->threshold,
            $data['cooldown_until'],
            $data['last_error']
        );
    }

    public function recordFailure(string $error): void
    {
        $status = $this->getStatus();
        $newFailCount = $status->failCount + 1;

        $newState = $newFailCount >= $this->threshold ? 'open' : 'closed';
        $cooldownUntil = $newState === 'open' ? wp_date('U') + $this->cooldown : null;

        $this->saveState($newState, $newFailCount, $cooldownUntil, $error);

        if ($newState === 'open') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new CircuitOpenException(
                $this->transientKey, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                $newFailCount, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                (int) $cooldownUntil,
                'Circuit breaker opened due to failure threshold exceeded'
            );
        }
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
            'last_error' => $lastError,
        ];

        set_transient($this->transientKey, $data, $this->cooldown + 60);
    }

    private function retrieveOrInitializeTransient(): array
    {
        $data = get_transient($this->transientKey);

        if ($data === false) {
            return [
                'state' => 'closed',
                'fail_count' => 0,
                'cooldown_until' => null,
                'last_error' => null,
            ];
        }

        return $data;
    }

    private function autoRecoverIfExpired(array $data): array
    {
        if (
            $data['state'] === 'open' &&
            $data['cooldown_until'] !== null &&
            (int) wp_date('U') >= $data['cooldown_until']
        ) {
            $data['state'] = 'half-open';
            $data['fail_count'] = 0;
            $data['cooldown_until'] = null;

            $this->saveState(
                $data['state'],
                $data['fail_count'],
                $data['cooldown_until'],
                $data['last_error']
            );
        }

        return $data;
    }

    private function sanitizeErrorMessage(array $data): array
    {
        if ($data['last_error'] !== null) {
            $data['last_error'] = substr($data['last_error'], 0, 100);
        }

        return $data;
    }
}

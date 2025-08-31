<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Infra\CircuitStorage;
use SmartAlloc\Infra\TransientCircuitStorage;

/**
 * Circuit Breaker pattern implementation
 */
final class CircuitBreaker
{
    private int $threshold;
    private int $cooldown;
    private $halfOpenCallback;
    private CircuitStorage $storage;

    public function __construct(
        int $threshold = 5,
        int $cooldown = 60,
        ?callable $halfOpenCallback = null,
        ?CircuitStorage $storage = null
    ) {
        $this->threshold = $threshold;
        $this->cooldown = $cooldown;
        $this->halfOpenCallback = $halfOpenCallback;
        $this->storage = $storage ?: new TransientCircuitStorage();
    }

    /**
     * Check if circuit is open before making a call
     */
    public function guard(string $name): void
    {
        $state = $this->getState($name);
        
        if ($state['state'] === 'open') {
            $openedAt = strtotime($state['opened_at']);
            $resetTime = $openedAt + 60; // 1 minute cooldown
            
            if (time() < $resetTime) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new \RuntimeException("Circuit breaker open: $name");
            }
            
            // Try to close the circuit
            $this->setState($name, 'half', 0, null);
        }
    }

    /**
     * Record a successful call
     */
    public function success(string $name): void
    {
        $this->setState($name, 'closed', 0, null);
    }

    /**
     * Record a failed call
     */
    public function failure(string $name, int $threshold = 5): void
    {
        $state = $this->getState($name);
        $failures = $state['failures'] + 1;
        
        if ($failures >= $threshold) {
            $this->setState($name, 'open', $failures, current_time('mysql'));
        } else {
            $this->setState($name, 'half', $failures, null);
        }
    }

    /**
     * Get current circuit state
     */
    private function getState(string $name): array
    {
        $row = $this->storage->get($name);
        if (!$row) {
            return [
                'state' => 'closed',
                'failures' => 0,
                'opened_at' => null,
            ];
        }
        return $row;
    }

    /**
     * Set circuit state
     */
    private function setState(string $name, string $state, int $failures, ?string $openedAt): void
    {
        $this->storage->put(
            $name,
            [
                'state' => $state,
                'failures' => $failures,
                'opened_at' => $openedAt,
            ],
            $this->cooldown
        );
    }

    /**
     * Reset a circuit breaker
     */
    public function reset(string $name): void
    {
        $this->setState($name, 'closed', 0, null);
    }

    /**
     * Execute operation with circuit breaker protection.
     *
     * @param callable $operation
     * @param array<int,mixed> $args
     * @throws \Throwable
     */
    public function protect(callable $operation, string $serviceName, array $args = []): mixed
    {
        $this->guard($serviceName);
        try {
            $result = $operation(...$args);
            $this->success($serviceName);
            return $result;
        } catch (\Throwable $e) {
            $this->failure($serviceName, $this->threshold);
            throw $e;
        }
    }

    /**
     * Get all circuit breakers status
     */
    public function getStatus(): array
    {
        return [];
    }

    /**
     * Get comprehensive status report
     */
    public function getStatusReport(): array
    {
        $status = $this->getStatus();
        
        return [
            'circuits' => $status,
            'summary' => [
                'total' => count($status),
                'closed' => count(array_filter($status, fn($s) => $s['state'] === 'closed')),
                'open' => count(array_filter($status, fn($s) => $s['state'] === 'open')),
                'half' => count(array_filter($status, fn($s) => $s['state'] === 'half'))
            ],
            'config' => [
                'threshold' => $this->threshold,
                'cooldown' => $this->cooldown,
                'has_half_open_callback' => $this->halfOpenCallback !== null
            ]
        ];
    }

    /**
     * Execute callback when circuit is half-open
     */
    public function executeHalfOpenCallback(string $name): mixed
    {
        if ($this->halfOpenCallback === null) {
            return null;
        }

        try {
            return call_user_func($this->halfOpenCallback, $name);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get circuit configuration
     */
    public function getConfig(): array
    {
        return [
            'threshold' => $this->threshold,
            'cooldown' => $this->cooldown,
            'has_half_open_callback' => $this->halfOpenCallback !== null
        ];
    }

    /**
     * Update circuit configuration
     */
    public function updateConfig(int $threshold, int $cooldown, $halfOpenCallback = null): void
    {
        $this->threshold = $threshold;
        $this->cooldown = $cooldown;
        $this->halfOpenCallback = $halfOpenCallback;
    }
} 
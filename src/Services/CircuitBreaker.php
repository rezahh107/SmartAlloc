<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Circuit Breaker pattern implementation
 */
final class CircuitBreaker
{
    private string $table;
    private int $threshold;
    private int $cooldown;
    private $halfOpenCallback;

    public function __construct(
        int $threshold = 5,
        int $cooldown = 60,
        ?callable $halfOpenCallback = null
    ) {
        global $wpdb;
        $this->table = $wpdb->prefix . 'salloc_circuit_breakers';
        $this->threshold = $threshold;
        $this->cooldown = $cooldown;
        $this->halfOpenCallback = $halfOpenCallback;
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
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE name = %s",
            $name
        ), 'ARRAY_A');
        
        if (!$row) {
            return [
                'state' => 'closed',
                'failures' => 0,
                'opened_at' => null
            ];
        }
        
        return $row;
    }

    /**
     * Set circuit state
     */
    private function setState(string $name, string $state, int $failures, ?string $openedAt): void
    {
        global $wpdb;
        
        $wpdb->replace($this->table, [
            'name' => $name,
            'state' => $state,
            'opened_at' => $openedAt,
            'meta_json' => wp_json_encode(['failures' => $failures])
        ]);
    }

    /**
     * Reset a circuit breaker
     */
    public function reset(string $name): void
    {
        $this->setState($name, 'closed', 0, null);
    }

    /**
     * Get all circuit breakers status
     */
    public function getStatus(): array
    {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY name",
            'ARRAY_A'
        );
        
        if (!$results) {
            return [];
        }
        
        foreach ($results as &$result) {
            $result['meta'] = json_decode($result['meta_json'] ?: '{}', true);
        }
        
        return $results;
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
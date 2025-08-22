<?php

declare(strict_types=1);

namespace SmartAlloc\Observability;

/**
 * Simple alert evaluator for test assertions.
 */
final class AlertService
{
    /** @var array<string,float> */
    private array $config;

    /** @var array<string,bool> */
    private array $triggered = [];

    /** @var array<int,string> */
    public array $events = [];

    public function __construct(?array $config = null)
    {
        $defaults = [
            'alloc_p95_ms' => 2000.0,
            'notify_failure_rate' => 0.05,
            'dlq_backlog' => 200.0,
        ];
        if ($config !== null) {
            $this->config = array_merge($defaults, $config);
        } elseif (function_exists('apply_filters')) {
            /** @var array<string,float> $cfg */
            $cfg = apply_filters('smartalloc_alert_thresholds', $defaults);
            $this->config = array_merge($defaults, $cfg);
        } else {
            $this->config = $defaults;
        }
    }

    public function check(string $metric, float $value): void
    {
        $threshold = $this->config[$metric] ?? null;
        if ($threshold === null) {
            return;
        }
        if ($value > $threshold) {
            if (empty($this->triggered[$metric])) {
                $this->triggered[$metric] = true;
                $this->events[] = $metric;
            }
        } else {
            $this->triggered[$metric] = false;
        }
    }
}

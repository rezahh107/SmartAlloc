<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Metrics;

/**
 * Lightweight in-memory metrics collector persisted via options.
 *
 * This implementation stores counters, gauges and timing samples in a
 * single WordPress option to avoid heavy database writes. It is
 * intentionally simple – no PII, no labels.
 */
final class MetricsCollector
{
    private const OPTION_KEY = 'smartalloc_metrics';

    /**
     * Increment a counter metric.
     */
    public function inc(string $key, int $value = 1): void
    {
        $data = $this->read();
        $data['counters'][$key] = ($data['counters'][$key] ?? 0) + $value;
        $this->write($data);
    }

    /**
     * Adjust a gauge metric by delta.
     */
    public function gauge(string $key, int $delta): void
    {
        $data = $this->read();
        $data['gauges'][$key] = ($data['gauges'][$key] ?? 0) + $delta;
        if ($data['gauges'][$key] < 0) {
            $data['gauges'][$key] = 0;
        }
        $this->write($data);
    }

    public function setGauge(string $key, int $value): void
    {
        $data = $this->read();
        $data['gauges'][$key] = max(0, $value);
        $this->write($data);
    }

    /**
     * Record a duration in milliseconds (stores last 10 samples).
     */
    public function observeDuration(string $key, int $ms): void
    {
        $data = $this->read();
        $samples = $data['timings'][$key] ?? [];
        $samples[] = $ms;
        if (count($samples) > 10) {
            array_shift($samples);
        }
        $data['timings'][$key] = $samples;
        $this->write($data);
    }

    /**
     * Retrieve all metrics with timestamp.
     *
     * @return array{counters:array<string,int>,gauges:array<string,int>,timings:array<string,array<int,int>>,ts:int}
     */
    public function all(): array
    {
        $data = $this->read();
        $data['ts'] = time();
        return $data;
    }

    /**
     * Reset metrics (used in tests).
     */
    public function reset(): void
    {
        if (function_exists('delete_option')) {
            delete_option(self::OPTION_KEY);
        } else {
            unset($GLOBALS['sa_options'][self::OPTION_KEY]);
        }
    }

    /**
     * @return array{counters:array<string,int>,gauges:array<string,int>,timings:array<string,array<int,int>>}
     */
    private function read(): array
    {
        $data = get_option(self::OPTION_KEY, []);
        return array_merge(
            ['counters' => [], 'gauges' => [], 'timings' => []],
            is_array($data) ? $data : []
        );
    }

    private function write(array $data): void
    {
        update_option(self::OPTION_KEY, $data, false);
    }
}

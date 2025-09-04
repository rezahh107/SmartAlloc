<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Infrastructure\Contracts\DbProxy;
use SmartAlloc\Infrastructure\WpDb\WpdbAdapter;
use Throwable;

/**
 * Metrics collection service
 */
class Metrics
{
    private DbProxy $db;
    private string $table;

    public function __construct(?DbProxy $db = null, ?string $table = null)
    {
        $this->db = $db ?? WpdbAdapter::fromGlobals();
        $this->table = $table ?? $this->db->getPrefix() . 'salloc_metrics';
    }

    public static function createDefault(): self
    {
        return new self(WpdbAdapter::fromGlobals());
    }

    /**
     * Increment a counter metric
     */
    public function inc(string $key, float $value = 1.0, array $labels = []): void
    {
        try {
            $this->db->insert($this->table, [
                'metric_key' => $key,
                'labels' => \wp_json_encode($labels),
                'value' => $value,
            ]);
        } catch (Throwable $e) {
            error_log('Metrics::inc: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    /**
     * Record a duration metric
     */
    public function observe(string $key, int $milliseconds, array $labels = []): void
    {
        $this->inc($key, (float) $milliseconds, $labels);
    }

    /**
     * Get metrics for a specific key
     */
    public function get(string $key, int $limit = 100): array
    {
        try {
            $sql = "SELECT metric_key, labels, value, ts FROM {$this->table} WHERE metric_key = %s ORDER BY ts DESC LIMIT %d";
            $results = $this->db->getResults($sql, [$key, $limit]);
        } catch (Throwable $e) {
            error_log('Metrics::get: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return [];
        }

        foreach ($results as &$result) {
            $result['labels'] = json_decode($result['labels'] ?: '[]', true);
        }

        return $results;
    }

    /**
     * Get aggregated metrics
     */
    public function getAggregated(string $key, string $aggregation = 'sum', int $hours = 24): array
    {
        $sqlAgg = match ($aggregation) {
            'sum' => 'SUM(value)',
            'avg' => 'AVG(value)',
            'count' => 'COUNT(*)',
            'min' => 'MIN(value)',
            'max' => 'MAX(value)',
            default => 'SUM(value)',
        };

        try {
            $sql = "SELECT {$sqlAgg} as value, labels FROM {$this->table} WHERE metric_key = %s AND ts >= DATE_SUB(NOW(), INTERVAL %d HOUR) GROUP BY labels ORDER BY value DESC";
            $results = $this->db->getResults($sql, [$key, $hours]);
        } catch (Throwable $e) {
            error_log('Metrics::getAggregated: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return [];
        }

        foreach ($results as &$result) {
            $result['labels'] = json_decode($result['labels'] ?: '[]', true);
        }

        return $results;
    }
}


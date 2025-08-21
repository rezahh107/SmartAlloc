<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Metrics collection service
 */
final class Metrics
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'salloc_metrics';
    }

    /**
     * Increment a counter metric
     */
    public function inc(string $key, float $value = 1.0, array $labels = []): void
    {
        global $wpdb;
        
        // @security-ok-sql
        $wpdb->insert($this->table, [
            'metric_key' => $key,
            'labels' => wp_json_encode($labels),
            'value' => $value,
        ]);
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
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT metric_key, labels, value, ts 
             FROM {$this->table} 
             WHERE metric_key = %s 
             ORDER BY ts DESC 
             LIMIT %d",
            $key,
            $limit
        ), ARRAY_A);
        
        if (!$results) {
            return [];
        }
        
        // Decode labels
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
        global $wpdb;
        
        $sql = match ($aggregation) {
            'sum' => 'SUM(value)',
            'avg' => 'AVG(value)',
            'count' => 'COUNT(*)',
            'min' => 'MIN(value)',
            'max' => 'MAX(value)',
            default => 'SUM(value)'
        };
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT {$sql} as value, labels
             FROM {$this->table} 
             WHERE metric_key = %s 
             AND ts >= DATE_SUB(NOW(), INTERVAL %d HOUR)
             GROUP BY labels
             ORDER BY value DESC",
            $key,
            $hours
        ), ARRAY_A);
        
        if (!$results) {
            return [];
        }
        
        // Decode labels
        foreach ($results as &$result) {
            $result['labels'] = json_decode($result['labels'] ?: '[]', true);
        }
        
        return $results;
    }
} 
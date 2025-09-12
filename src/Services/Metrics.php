<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use Psr\Log\LoggerInterface;
use SmartAlloc\Database\DbPort;
use Throwable;
use function wp_json_encode;

/**
 * Metrics collection service.
 */
class Metrics
{
    private DbPort $db;

    private string $table;

    private ?LoggerInterface $logger;

    public function __construct(DbPort $dbPort, string $tableName, ?LoggerInterface $logger = null)
    {
        $this->db     = $dbPort;
        $this->table  = $tableName;
        $this->logger = $logger;
    }

    private function log_error(string $method, Throwable $e): void
    {
        $context = [
            'method'    => $method,
            'table'     => $this->table,
            'exception' => $e->getMessage(),
        ];

        if ($this->logger) {
            $this->logger->error('Metrics database operation failed', $context);
        } else {
            \SmartAlloc\Support\LogHelper::error(
                'Metrics operation failed',
                ['method' => $method, 'table' => $this->table, 'exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Increment a counter metric.
     *
     * @param string $key    Metric key.
     * @param float  $value  Increment amount.
     * @param array  $labels Metric labels.
     */
    public function inc(string $key, float $value = 1.0, array $labels = []): void
    {
        try {
            $this->db->exec(
                "INSERT INTO {prefix}{$this->table} (metric_key, labels, value) VALUES (%s, %s, %f)",
                $key,
                wp_json_encode($labels),
                $value
            );
        } catch (Throwable $e) {
            $this->log_error(__METHOD__, $e);
        }
    }

    /**
     * Record a duration metric.
     *
     * @param string $key          Metric key.
     * @param int    $milliseconds Duration in milliseconds.
     * @param array  $labels       Metric labels.
     */
    public function observe(string $key, int $milliseconds, array $labels = []): void
    {
        $this->inc($key, (float) $milliseconds, $labels);
    }

    /**
     * Get metrics for a specific key.
     *
     * @param string $key   Metric key.
     * @param int    $limit Maximum rows to return.
     * @return array<int,array<string,mixed>>
     */
    public function get(string $key, int $limit = 100): array
    {
        try {
            $rows = $this->db->exec(
                "SELECT metric_key, labels, value, ts FROM {prefix}{$this->table} WHERE metric_key = %s ORDER BY ts DESC LIMIT %d",
                $key,
                $limit
            );
        } catch (Throwable $e) {
            $this->log_error(__METHOD__, $e);
            return [];
        }

        $rows = is_array($rows) ? $rows : [];
        foreach ($rows as &$row) {
            $row['labels'] = json_decode($row['labels'] ?: '[]', true);
        }

        return $rows;
    }

    /**
     * Get aggregated metrics.
     *
     * @param string $key         Metric key.
     * @param string $aggregation Aggregation type.
     * @param int    $hours       Hours to look back.
     * @return array<int,array<string,mixed>>
     */
    public function getAggregated(string $key, string $aggregation = 'sum', int $hours = 24): array
    {
        $sql_agg = match ($aggregation) {
            'sum'   => 'SUM(value)',
            'avg'   => 'AVG(value)',
            'count' => 'COUNT(*)',
            'min'   => 'MIN(value)',
            'max'   => 'MAX(value)',
            default => 'SUM(value)',
        };

        try {
            $rows = $this->db->exec(
                "SELECT {$sql_agg} as value, labels FROM {prefix}{$this->table} WHERE metric_key = %s AND ts >= DATE_SUB(NOW(), INTERVAL %d HOUR) GROUP BY labels ORDER BY value DESC",
                $key,
                $hours
            );
        } catch (Throwable $e) {
            $this->log_error(__METHOD__, $e);
            return [];
        }

        $rows = is_array($rows) ? $rows : [];
        foreach ($rows as &$row) {
            $row['labels'] = json_decode($row['labels'] ?: '[]', true);
        }

        return $rows;
    }
}

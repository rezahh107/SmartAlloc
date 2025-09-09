<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Export;

use InvalidArgumentException;
use SmartAlloc\Infra\Metrics\MetricsCollector;
use SmartAlloc\Domain\Export\CircuitBreaker;
use SmartAlloc\Compat\ThirdParty\JalaliDateConverter;

/**
 * Exporter service bridging database and Excel exporter.
 *
 * @phpcs:ignoreFile
 */
class ExporterService
{
    private ExcelExporter $excelExporter;

    public function __construct(
        private $wpdb = null,
        ?ExcelExporter $excelExporter = null,
        private MetricsCollector $metrics = new MetricsCollector(),
        private CircuitBreaker $breaker = new CircuitBreaker(new MetricsCollector())
    ) {
        $this->wpdb          = $wpdb ?? $GLOBALS['wpdb'];
        $this->excelExporter = $excelExporter ?? new ExcelExporter($this->wpdb);
    }

    /**
     * Retrieve raw export data by id.
     *
     * @return array<int,array<string,mixed>>
     */
    public function exportData(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid id');
        }

        $table = $this->wpdb->prefix . 'exports';
        $sql   = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", absint($id));

        /** @var list<array<string,mixed>> $results */
        $results = $this->wpdb->get_results($sql, ARRAY_A) ?: [];

        return $results;
    }

    /**
     * Generate an export and record metadata.
     *
     * @return array{file:string,url:string,rows_exported:int}
     */
    public function generate(string $from, string $to, ?int $batch = null): array
    {
        return JalaliDateConverter::withDateI18nBypassed(function () use ($from, $to, $batch): array {
            $start = microtime(true);
            $this->metrics->gauge('exports_in_progress', 1);

            $delay = 0;
            $partial = [];
            if (function_exists('apply_filters')) {
                $delay = (int) apply_filters('smartalloc_test_fault_latency_ms', 0);
                $partial = apply_filters('smartalloc_test_fault_partial_service', []);
                if (isset($GLOBALS['filters']['smartalloc_test_fault_latency_ms'])) {
                    $delay = (int) $GLOBALS['filters']['smartalloc_test_fault_latency_ms']($delay);
                }
                if (isset($GLOBALS['filters']['smartalloc_test_fault_partial_service'])) {
                    $partial = $GLOBALS['filters']['smartalloc_test_fault_partial_service']($partial);
                }
            }
            if ($delay > 0) {
                usleep($delay * 1000);
            }
            if (!empty($partial['export'])) {
                throw new \RuntimeException('export unavailable');
            }

            $upload = wp_upload_dir();
            $dir    = trailingslashit($upload['basedir']) . 'smartalloc/exports/' . gmdate('Y/m/');
            wp_mkdir_p($dir);

            $table = $this->wpdb->prefix . 'allocations';
            if ($batch !== null) {
                $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE batch_id = %d", absint($batch));
            } else {
                $sql = $this->wpdb->prepare(
                    "SELECT * FROM {$table} WHERE created_at BETWEEN %s AND %s",
                    $from . ' 00:00:00',
                    $to . ' 23:59:59'
                );
            }
            /** @var list<array<string,mixed>> $rows */
            $rows = $this->wpdb->get_results($sql, ARRAY_A) ?: [];
            $rows = $this->normalizeDates($rows);
            $exporter = new ExcelExporter($this->wpdb, null, $dir);

            try {
                $result   = $exporter->exportFromRows($rows);
                $path     = $result['path'];
                $filename = basename($path);
                $size     = is_file($path) ? (int) filesize($path) : 0;
                $checksum = is_file($path) ? hash_file('sha256', $path) : '';
                $rowCount = count($rows);

                $filters = $batch !== null
                    ? array('mode' => 'batch', 'batch' => $batch)
                    : array('mode' => 'date-range', 'from' => $from, 'to' => $to);

                $registry = $this->wpdb->prefix . 'smartalloc_exports';
                $this->wpdb->insert($registry, array(
                    'filename'   => $filename,
                    'path'       => $path,
                    'filters'    => wp_json_encode($filters),
                    'size'       => $size,
                    'rows'       => $rowCount,
                    'checksum'   => $checksum ?: null,
                    'status'     => 'Valid',
                    'created_at' => current_time('mysql', true),
                ));

                $url = str_replace(trailingslashit($upload['basedir']), trailingslashit($upload['baseurl']), $path);
                $this->metrics->inc('exports_total');
                return array(
                    'file'          => $path,
                    'url'           => $url,
                    'rows_exported' => $rowCount,
                );
            } catch (\Throwable $e) {
                $this->metrics->inc('exports_failed');
                $this->breaker->recordFailure();
                throw $e;
            } finally {
                $this->metrics->gauge('exports_in_progress', -1);
                $duration = (int) round((microtime(true) - $start) * 1000);
                $this->metrics->observeDuration('export_duration_ms', $duration);
            }
        });
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function normalizeDates(array $rows): array
    {
        return array_map(
            static function (array $row): array {
                foreach ($row as $k => $v) {
                    if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}:\d{2})?$/', $v)) {
                        try {
                            $dt = new \DateTimeImmutable($v, new \DateTimeZone('UTC'));
                            $row[$k] = $dt->format('Y-m-d\TH:i:s\Z');
                        } catch (\Throwable) {
                            // ignore invalid dates
                        }
                    }
                }
                return $row;
            },
            $rows
        );
    }

    /**
     * Retrieve recent exports.
     *
     * @return list<array<string,mixed>>
     */
    public function getRecent(int $limit = 20): array
    {
        if ($limit <= 0) {
            throw new InvalidArgumentException('Invalid limit');
        }
        $table = $this->wpdb->prefix . 'smartalloc_exports';
        $sql   = $this->wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit);
        /** @var list<array<string,mixed>> $rows */
        $rows = $this->wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(
            static function (array $row): array {
                return array(
                    'filename'   => $row['filename'] ?? '',
                    'size'       => (int) ($row['size'] ?? 0),
                    'checksum'   => $row['checksum'] ?? '',
                    'rows'       => (int) ($row['rows'] ?? 0),
                    'created_at' => $row['created_at'] ?? '',
                    'status'     => $row['status'] ?? 'Valid',
                );
            },
            $rows
        );
    }
}

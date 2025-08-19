<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Export;

use InvalidArgumentException;

/**
 * Exporter service bridging database and Excel exporter.
 *
 * @phpcs:ignoreFile
 */
class ExporterService
{
    private ExcelExporter $excelExporter;

    public function __construct(private $wpdb = null, ?ExcelExporter $excelExporter = null)
    {
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
        $rows     = $this->wpdb->get_results($sql, ARRAY_A) ?: [];
        $exporter = new ExcelExporter($this->wpdb, null, $dir);
        $result   = $exporter->exportFromRows($rows);
        $path     = $result['path'];
        $filename = basename($path);
        $size     = is_file($path) ? (int) filesize($path) : 0;
        $checksum = is_file($path) ? hash_file('sha256', $path) : '';

        $filters = $batch !== null
            ? array('mode' => 'batch', 'batch' => $batch)
            : array('mode' => 'date-range', 'from' => $from, 'to' => $to);

        $registry = $this->wpdb->prefix . 'smartalloc_exports';
        $this->wpdb->insert($registry, array(
            'filename'   => $filename,
            'path'       => $path,
            'filters'    => wp_json_encode($filters),
            'size'       => $size,
            'checksum'   => $checksum ?: null,
            'created_at' => current_time('mysql'),
        ));

        $url = str_replace(trailingslashit($upload['basedir']), trailingslashit($upload['baseurl']), $path);

        return array(
            'file'          => $path,
            'url'           => $url,
            'rows_exported' => count($rows),
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
        return $rows;
    }
}

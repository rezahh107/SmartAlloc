<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Export;

use InvalidArgumentException;
use SmartAlloc\Infra\Export\ExcelExporter;

/**
 * Exporter using WordPress database access with table name safety.
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
        $results = $this->wpdb->get_results($sql, 'ARRAY_A') ?: [];

        return $results;
    }

    /**
     * Export allocations by batch id to Excel.
     *
     * @return array{path:string, spreadsheet:\PhpOffice\PhpSpreadsheet\Spreadsheet}
     */
    public function exportToExcelByBatch(int $batchId): array
    {
        $table = $this->wpdb->prefix . 'allocations';
        $sql   = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE batch_id = %d",
            absint($batchId)
        );
        /** @var list<array<string,mixed>> $rows */
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A') ?: [];

        return $this->excelExporter->exportFromRows($rows);
    }

    /**
     * Export allocations by date range to Excel.
     *
     * @return array{path:string, spreadsheet:\PhpOffice\PhpSpreadsheet\Spreadsheet}
     */
    public function exportToExcelByDateRange(string $from, string $to): array
    {
        $table = $this->wpdb->prefix . 'allocations';
        $sql   = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE created_at BETWEEN %s AND %s",
            $from,
            $to
        );
        /** @var list<array<string,mixed>> $rows */
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A') ?: [];

        return $this->excelExporter->exportFromRows($rows);
    }
}

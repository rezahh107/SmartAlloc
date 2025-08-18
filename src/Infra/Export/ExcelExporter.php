<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Excel exporter aligned with SmartAlloc spec.
 */
class ExcelExporter
{
    private array $config;
    private string $configPath;
    private string $exportDir;

    private static int $batchCounter = 1;
    private static string $lastDate = '';
    private static int $dailyCounter = 0;

    public function __construct(private $wpdb, ?string $configPath = null, ?string $exportDir = null)
    {
        $this->configPath = $configPath ?? dirname(__DIR__, 3) . '/SmartAlloc_Exporter_Config_v1.json';
        $this->exportDir  = $exportDir  ?? sys_get_temp_dir();
        $configContent    = file_get_contents($this->configPath) ?: '{}';
        $this->config     = json_decode($configContent, true) ?: [];
    }

    /**
     * Export data by batch id.
     *
     * @return array{path:string, spreadsheet:Spreadsheet}
     */
    public function exportByBatchId(int $batchId): array
    {
        $table = $this->wpdb->prefix . 'allocations';
        $sql   = $this->wpdb->prepare("SELECT * FROM {$table} WHERE batch_id = %d", absint($batchId));
        /** @var list<array<string,mixed>> $rows */
        $rows  = $this->wpdb->get_results($sql, 'ARRAY_A') ?: [];
        return $this->buildSpreadsheet($rows);
    }

    /**
     * Export data by date range.
     *
     * @return array{path:string, spreadsheet:Spreadsheet}
     */
    public function exportByDateRange(string $from, string $to): array
    {
        $table = $this->wpdb->prefix . 'allocations';
        $sql   = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE created_at BETWEEN %s AND %s",
            $from,
            $to
        );
        /** @var list<array<string,mixed>> $rows */
        $rows  = $this->wpdb->get_results($sql, 'ARRAY_A') ?: [];
        return $this->buildSpreadsheet($rows);
    }

    /**
     * Build spreadsheet from rows and save to disk.
     *
     * @param list<array<string,mixed>> $rows
     * @return array{path:string, spreadsheet:Spreadsheet}
     */
    public function exportFromRows(array $rows): array
    {
        return $this->buildSpreadsheet($rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{path:string, spreadsheet:Spreadsheet}
     */
    private function buildSpreadsheet(array $rows): array
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $summarySheet = $spreadsheet->createSheet()->setTitle('Summary');
        $errorSheet   = $spreadsheet->createSheet()->setTitle('Errors');

        $this->buildSummarySheet($summarySheet, $rows);
        $this->buildErrorsSheet($errorSheet, $rows);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheet->freezePane('A2');
            $highestColumn = $sheet->getHighestColumn();
            $highestIndex  = Coordinate::columnIndexFromString($highestColumn);
            for ($i = 1; $i <= $highestIndex; $i++) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }
        }

        $filename = $this->generateFilename();
        $path     = $this->exportDir . DIRECTORY_SEPARATOR . $filename;
        $writer   = new Xlsx($spreadsheet);
        $writer->save($path);

        return ['path' => $path, 'spreadsheet' => $spreadsheet];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function buildSummarySheet($sheet, array $rows): void
    {
        $allocated = $this->countByStatus($rows, 'allocated');
        $manual    = $this->countByStatus($rows, 'manual');
        $rejected  = $this->countByStatus($rows, 'rejected');
        $total     = count($rows);
        $fuzzy     = count(array_filter($rows, static fn($r) => !empty($r['fuzzy'])));

        $data = [
            'allocated'      => $allocated,
            'manual'         => $manual,
            'rejected'       => $rejected,
            'capacity_usage' => $total > 0 ? round($allocated / $total, 2) : 0,
            'fuzzy_rate'     => $total > 0 ? round($fuzzy / $total, 2) : 0,
        ];

        $columns = $this->config['Summary'] ?? [];
        $col = 1;
        foreach ($columns as $column) {
            $key = $column['key'] ?? '';
            $label = $column['label'] ?? $key;
            $sheet->setCellValueByColumnAndRow($col, 1, $label);
            $sheet->setCellValueByColumnAndRow($col, 2, $data[$key] ?? '');
            $col++;
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function buildErrorsSheet($sheet, array $rows): void
    {
        $columns = $this->config['Errors'] ?? [];
        $col = 1;
        foreach ($columns as $column) {
            $label = $column['label'] ?? '';
            $sheet->setCellValueByColumnAndRow($col, 1, $label);
            $col++;
        }

        $rowIndex = 2;
        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            if ($status === 'rejected' || $status === 'manual') {
                $col = 1;
                foreach ($columns as $column) {
                    $key = $column['key'] ?? '';
                    $sheet->setCellValueByColumnAndRow($col, $rowIndex, $row[$key] ?? '');
                    $col++;
                }
                $rowIndex++;
            }
        }
    }

    /**
     * Count rows by status
     *
     * @param list<array<string,mixed>> $rows
     */
    private function countByStatus(array $rows, string $status): int
    {
        return count(array_filter($rows, static fn($r) => ($r['status'] ?? '') === $status));
    }

    private function generateFilename(): string
    {
        $date = date('Y_m_d');
        if (self::$lastDate !== $date) {
            self::$dailyCounter = 0;
            self::$lastDate     = $date;
        }
        self::$dailyCounter++;
        $daily = str_pad((string) self::$dailyCounter, 4, '0', STR_PAD_LEFT);

        $batch = str_pad((string) self::$batchCounter, 3, '0', STR_PAD_LEFT);
        self::$batchCounter++;

        return sprintf('SabtExport-ALLOCATED-%s-%s-B%s.xlsx', $date, $daily, $batch);
    }
}


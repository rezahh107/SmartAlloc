<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Settings as SpreadsheetSettings;
use PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory;
use SmartAlloc\Infra\Settings\Settings;

/**
 * Excel exporter aligned with SmartAlloc spec.
 */
class ExcelExporter
{
    private array $config;
    private string $configPath;
    private string $exportDir;

    private CountersRepository $counters;

    public function __construct(private $wpdb, ?string $configPath = null, ?string $exportDir = null, ?CountersRepository $counters = null)
    {
        $this->configPath = $configPath ?? dirname(__DIR__, 3) . '/SmartAlloc_Exporter_Config_v1.json';
        $this->exportDir  = $exportDir  ?? sys_get_temp_dir();
        $configContent    = file_get_contents($this->configPath) ?: '{}';
        $this->config     = json_decode($configContent, true) ?: [];
        $this->counters   = $counters ?? new CountersRepository($this->wpdb);
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
        if (count($rows) > 1000) {
            SpreadsheetSettings::setCacheStorageMethod(CachedObjectStorageFactory::cache_to_discISAM, [
                'dir' => sys_get_temp_dir(),
            ]);
        }

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
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);

        $retention = Settings::getExportRetentionDays();
        if ($retention > 0) {
            try {
                $this->purgeOldExports($retention);
            } catch (\Throwable $e) {
                // ignore cleanup failures
            }
        }

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
        $fuzzyAuto = count(array_filter($rows, static fn($r) => ($r['status'] ?? '') === 'allocated' && !empty($r['fuzzy'])));
        $fuzzyManual = count(array_filter($rows, static fn($r) => ($r['status'] ?? '') === 'manual' && !empty($r['fuzzy'])));
        $fuzzyTotal  = count(array_filter($rows, static fn($r) => !empty($r['fuzzy'])));

        $data = [
            'allocated'         => $allocated,
            'manual'            => $manual,
            'rejected'          => $rejected,
            'capacity_usage'    => $total > 0 ? round($allocated / $total, 2) : 0,
            'fuzzy_auto_rate'   => $allocated > 0 ? round($fuzzyAuto / $allocated, 2) : 0,
            'fuzzy_manual_rate' => $manual > 0 ? round($fuzzyManual / $manual, 2) : 0,
            'fuzzy_rate'        => $total > 0 ? round($fuzzyTotal / $total, 2) : 0,
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

    private function getNextCounters(): array
    {
        return $this->counters->getNextCounters();
    }

    private function generateFilename(): string
    {
        [$daily, $batch] = $this->getNextCounters();
        $date   = date('Y_m_d');
        $dailyS = str_pad((string) $daily, 4, '0', STR_PAD_LEFT);
        $batchS = str_pad((string) $batch, 3, '0', STR_PAD_LEFT);

        return sprintf('SabtExport-ALLOCATED-%s-%s-B%s.xlsx', $date, $dailyS, $batchS);
    }

    private function purgeOldExports(int $days): void
    {
        $files = glob($this->exportDir . DIRECTORY_SEPARATOR . '*.xlsx');
        if ($files === false) {
            return;
        }
        $threshold = time() - ($days * 86400);
        foreach ($files as $file) {
            if (is_file($file) && @filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }
}


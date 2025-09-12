<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SmartAlloc\Infra\GF\SabtEntryMapper;

/**
 * Simple Sabt exporter that maps Gravity Forms entries
 * and writes an Excel workbook with Summary and Errors sheets.
 *
 * This service is deterministic and writes an export report
 * to artifacts/export/export-report.json.
 */
final class SabtExporter
{
    private array $config;
    private static int $dailyCounter = 0;
    private static int $batchCounter = 0;

    public function __construct(?string $configPath = null)
    {
        $path = $configPath ?? dirname(__DIR__, 3) . '/config/SmartAlloc_Exporter_Config_v1.json';
        $json = file_get_contents($path) ?: '{}';
        $this->config = json_decode($json, true) ?: [];
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return array{path:string, valid:int, errors:int}
     */
    public function exportFromEntries(array $entries): array
    {
        $mapper = new SabtEntryMapper();
        $valid = [];
        $errors = [];

        foreach ($entries as $idx => $entry) {
            $mapped = $mapper->mapEntry($entry);
            if ($mapped['ok']) {
                $valid[] = $mapped['student'];
            } else {
                $errors[] = ['entry' => $idx, 'code' => $mapped['code'] ?? 'unknown'];
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Summary');

        $columns = $this->config['columns'] ?? [];
        $col = 1;
        foreach ($columns as $column) {
            $label = $column['label'] ?? ($column['field'] ?? '');
            $sheet->setCellValueByColumnAndRow($col, 1, $label);
            $col++;
        }
        $row = 2;
        foreach ($valid as $student) {
            $col = 1;
            foreach ($columns as $column) {
                $field = $column['field'] ?? '';
                $sheet->setCellValueByColumnAndRow($col, $row, (string)($student[$field] ?? ''));
                $col++;
            }
            $row++;
        }

        $errSheet = $spreadsheet->createSheet();
        $errSheet->setTitle('Errors');
        $errSheet->setCellValue('A1', 'Entry');
        $errSheet->setCellValue('B1', 'Error');
        $r = 2;
        foreach ($errors as $err) {
            $errSheet->setCellValueByColumnAndRow(1, $r, (string) $err['entry']);
            $errSheet->setCellValueByColumnAndRow(2, $r, (string) $err['code']);
            $r++;
        }

        $filename = $this->generateFilename();
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $this->writeReport(count($entries), count($valid), count($errors));

        return ['path' => $path, 'valid' => count($valid), 'errors' => count($errors)];
    }

    private function generateFilename(): string
    {
        self::$dailyCounter++;
        self::$batchCounter++;
        $date = gmdate('Y_m_d');
        return sprintf('SabtExport-ALLOCATED-%s-%04d-B%03d.xlsx', $date, self::$dailyCounter, self::$batchCounter);
    }

    private function writeReport(int $total, int $valid, int $errors): void
    {
        $root = dirname(__DIR__, 3);
        $dir = $root . '/artifacts/export';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $report = [
            'total' => $total,
            'valid' => $valid,
            'errors' => $errors,
        ];
        $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        \SmartAlloc\Infra\FS\Filesystem::write($dir . '/export-report.json', (string) $json);
    }
}

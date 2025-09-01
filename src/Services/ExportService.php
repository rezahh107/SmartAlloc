<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Migrations\ExportLogMigrator;
use SmartAlloc\Utils\Digits;
use SmartAlloc\Utils\Validators;

final class ExportService
{
    private ExporterConfig $config;
    private static int $dailyCounter = 0;
    private static int $batchCounter = 0;
    private static int $logSeq = 1;

    public function __construct(private TableResolver $tables, ?ExporterConfig $config = null)
    {
        $this->config = $config ?: ExporterConfig::load();
    }

    /**
     * @param array<string,mixed> $opts
     * @return array{ok:bool,file:string,log_id:int,rows_ok:int,rows_error:int}
     */
    public function export(FormContext $ctx, array $opts = []): array
    {
        global $wpdb;
        ExportLogMigrator::ensureTables($wpdb);
        $filename = $this->generateFilename();
        $filePath = $this->buildPath($filename);
        $logId = $this->startLog($ctx->formId, $filename);

        $rows = $this->gatherRows($ctx, $opts);
        [$rows, $errors] = $this->normalize($rows);
        [$valid, $errors] = $this->validate($rows, $errors);
        $sheets = $this->mapToSheets($valid);
        $this->writeXlsx($sheets, $filePath);
        $this->bulkInsertErrors($errors);
        $this->finishLog($logId, count($valid), count($errors), null);

        return [
            'ok' => true,
            'file' => $filePath,
            'log_id' => $logId,
            'rows_ok' => count($valid),
            'rows_error' => count($errors),
        ];
    }

    /**
     * Stream large exports with low memory footprint.
     *
     * @param array<string,string> $filters
     */
    public function streamExport(array $filters): void
    {
        $spreadsheet = new Spreadsheet();
        $writer      = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->setOffice2003Compatibility(true);
        $writer->setUseDiskCaching(true, sys_get_temp_dir());

        $sheet = $spreadsheet->getActiveSheet();
        $this->processInChunks($filters, $sheet);

        $writer->save('php://output');
    }

    /**
     * Stream rows from database in chunks to limit memory usage.
     *
     * @param array<string,string> $filters
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     */
    private function processInChunks(array $filters, $sheet): void
    {
        global $wpdb;

        $row       = 1;
        $offset    = 0;
        $chunkSize = 500;
        $max       = isset($filters['limit']) ? (int) $filters['limit'] : PHP_INT_MAX;
        $ctx       = new FormContext(0);
        $table     = $this->tables->allocations($ctx);

        do {
            $remaining = $max - ($row - 1);
            if ($remaining <= 0) {
                break;
            }

            $current = (int) min($chunkSize, $remaining);
            $sql     = DbSafe::mustPrepare(
                "SELECT id,national_id,mobile,postal FROM {$table} LIMIT %d OFFSET %d",
                [$current, $offset]
            );

            /** @var list<object> $results */
            $results = $wpdb->get_results($sql) ?: []; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            foreach ($results as $allocation) {
                $sheet->setCellValueByColumnAndRow(1, $row, (string) $allocation->id);
                $sheet->setCellValueByColumnAndRow(2, $row, (string) ($allocation->national_id ?? ''));
                $sheet->setCellValueByColumnAndRow(3, $row, (string) ($allocation->mobile ?? ''));
                $sheet->setCellValueByColumnAndRow(4, $row, (string) ($allocation->postal ?? ''));
                $row++;
            }

            $offset += $current;
        } while ($results !== [] && ($row - 1) < $max);
    }

    /** @param array<int,array<string,string>> $rows */
    private function normalize(array $rows): array
    {
        $errors = [];
        foreach ($rows as &$row) {
            foreach (['national_id','mobile','postal','hekmat'] as $field) {
                if (isset($row[$field])) {
                    $row[$field] = Digits::stripNonDigits(Digits::fa2en($row[$field]));
                }
            }
        }
        return [$rows, $errors];
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @param array<int,array<string,string>> $errors
     * @return array{0:array<int,array<string,string>>,1:array<int,array<string,string>>}
     */
    private function validate(array $rows, array $errors): array
    {
        foreach ($rows as $i => $row) {
            if (!Validators::nationalIdIr($row['national_id'] ?? '')) {
                $errors[] = ['row' => $i, 'column' => 'national_id', 'message' => 'invalid'];
                unset($rows[$i]);
                continue;
            }
            if (!Validators::mobileIr($row['mobile'] ?? '')) {
                $errors[] = ['row' => $i, 'column' => 'mobile', 'message' => 'invalid'];
                unset($rows[$i]);
                continue;
            }
            if (!Validators::postal10($row['postal'] ?? '')) {
                $errors[] = ['row' => $i, 'column' => 'postal', 'message' => 'invalid'];
                unset($rows[$i]);
                continue;
            }
            if (!Validators::digits16($row['hekmat'] ?? '')) {
                $errors[] = ['row' => $i, 'column' => 'hekmat', 'message' => 'invalid'];
                unset($rows[$i]);
            }
        }
        return [array_values($rows), $errors];
    }

    /**
     * @param array<string,list<array<string,string>>> $sheets
     */
    private function writeXlsx(array $sheets, string $file): void
    {
        $spreadsheet = new Spreadsheet();
        $sheetNames = array_keys($sheets);
        foreach ($sheetNames as $idx => $name) {
            $sheet = $idx === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle((string) $name);
            $columns = $this->config->data['sheets'][$name] ?? [];
            $rowIdx = 1;
            foreach ($sheets[$name] as $row) {
                foreach ($columns as $colIdx => $field) {
                    $value = (string) ($row[$field] ?? '');
                    if (in_array($field, $this->config->data['string_fields'], true)) {
                        $sheet->setCellValueExplicitByColumnAndRow($colIdx + 1, $rowIdx, $value, DataType::TYPE_STRING);
                    } else {
                        $sheet->setCellValueByColumnAndRow($colIdx + 1, $rowIdx, $value);
                    }
                }
                $rowIdx++;
            }
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save($file);
    }

    /** @param array<int,array<string,string>> $rows */
    private function mapToSheets(array $rows): array
    {
        return [
            'Sheet2' => $rows,
            'Sheet5' => [],
            '9394' => [],
        ];
    }

    /** @return array<int,array<string,string>> */
    private function gatherRows(FormContext $ctx, array $opts): array
    {
        // Minimal stub: return one sample row.
        unset($ctx, $opts); // @phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        return [[
            'national_id' => '۰۰۰۰۰۰۰۰۰۰',
            'mobile' => '۰۹۱۲۳۴۵۶۷۸۹',
            'postal' => '۰۱۲۳۴۵۶۷۸۹',
            'hekmat' => '۱۲۳۴۵۶۷۸۹۰۱۲۳۴۵۶',
        ]];
    }

    /** @param array<int,array{row:int,column:string,message:string}> $errors */
    private function bulkInsertErrors(array $errors): void
    {
        if (!$errors) {
            return;
        }
        global $wpdb;
        $rows = [];
        foreach ($errors as $e) {
            $rows[] = [
                $e['allocation_id'],
                $e['error_type'],
                $e['error_message'],
                current_time('mysql', true),
            ];
        }
        $values = DbSafe::mustPrepareMany('(%d,%s,%s,%s)', $rows);
        $sql = sprintf(
            'INSERT INTO %s (allocation_id,error_type,message,created_at) VALUES %s',
            $this->tables->exportErrors(),
            implode(',', $values)
        );
        $wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    private function startLog(int $formId, string $fileName): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'smartalloc_export_log';
        $sql = DbSafe::mustPrepare(
            "INSERT INTO $table (form_id,file_name,status,rows_ok,rows_error,started_at) VALUES (%d,%s,%s,%d,%d,%s)",
            [$formId, $fileName, 'started', 0, 0, current_time('mysql', 1)]
        );
        $wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        return self::$logSeq++;
    }

    private function finishLog(int $logId, int $ok, int $err, ?string $errorText): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'smartalloc_export_log';
        $sql = DbSafe::mustPrepare(
            "UPDATE $table SET status=%s, rows_ok=%d, rows_error=%d, finished_at=%s, error_text=%s WHERE id=%d",
            ['completed', $ok, $err, current_time('mysql', 1), $errorText, $logId]
        );
        $wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    private function generateFilename(): string
    {
        self::$dailyCounter++;
        self::$batchCounter++;
        $date = gmdate('Y_m_d');
        return sprintf('SabtExport-ALLOCATED-%s-%04d-B%03d.xlsx', $date, self::$dailyCounter, self::$batchCounter);
    }

    private function buildPath(string $filename): string
    {
        $upload = wp_upload_dir();
        $base = rtrim($upload['basedir'], DIRECTORY_SEPARATOR) . '/smart-alloc';
        if (!is_dir($base)) {
            wp_mkdir_p($base);
        }
        return $base . '/' . $filename;
    }
}


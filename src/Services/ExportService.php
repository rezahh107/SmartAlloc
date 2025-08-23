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
        $this->bulkInsertErrors($logId, $errors);
        $this->finishLog($logId, count($valid), count($errors), null);

        return [
            'ok' => true,
            'file' => $filePath,
            'log_id' => $logId,
            'rows_ok' => count($valid),
            'rows_error' => count($errors),
        ];
    }

    /** @param array<int,array<string,string>> $rows */
    private function normalize(array $rows): array
    {
        $errors = [];
        foreach ($rows as $i => &$row) {
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
        return [[
            'national_id' => '۰۰۰۰۰۰۰۰۰۰',
            'mobile' => '۰۹۱۲۳۴۵۶۷۸۹',
            'postal' => '۰۱۲۳۴۵۶۷۸۹',
            'hekmat' => '۱۲۳۴۵۶۷۸۹۰۱۲۳۴۵۶',
        ]];
    }

    /** @param array<int,array{row:int,column:string,message:string}> $errors */
    private function bulkInsertErrors(int $logId, array $errors): void
    {
        if (!$errors) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'smartalloc_export_errors';
        $chunks = [];
        foreach ($errors as $e) {
            $chunks[] = DbSafe::mustPrepare(
                "INSERT INTO $table (export_id,form_id,row_index,column_name,message) VALUES (%d,%d,%d,%s,%s)",
                [$logId, $e['form_id'] ?? 0, $e['row'], $e['column'], $e['message']]
            );
        }
        foreach ($chunks as $sql) {
            $wpdb->query($sql);
        }
    }

    private function startLog(int $formId, string $fileName): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'smartalloc_export_log';
        $sql = DbSafe::mustPrepare(
            "INSERT INTO $table (form_id,file_name,status,rows_ok,rows_error,started_at) VALUES (%d,%s,%s,%d,%d,%s)",
            [$formId, $fileName, 'started', 0, 0, current_time('mysql', 1)]
        );
        $wpdb->query($sql);
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
        $wpdb->query($sql);
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


<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Enhanced Export Service with config-driven export functionality
 */
final class ExportService
{
    private string $configPath;
    private array $config = [];
    private Logging $logger;
    private Metrics $metrics;

    public function __construct(Logging $logger, Metrics $metrics)
    {
        $this->logger = $logger;
        $this->metrics = $metrics;
        $this->configPath = $this->getConfigPath();
        $this->loadConfig();
    }

    /**
     * Get configuration file path
     */
    private function getConfigPath(): string
    {
        $uploadDir = wp_upload_dir();
        $configPath = trailingslashit($uploadDir['basedir']) . 'smart-alloc/SmartAlloc_Exporter_Config_v1.json';
        
        if (!file_exists($configPath)) {
            // Fallback to plugin directory
            $configPath = dirname(__DIR__, 2) . '/SmartAlloc_Exporter_Config_v1.json';
        }
        
        return $configPath;
    }

    /**
     * Load export configuration
     */
    private function loadConfig(): void
    {
        if (!file_exists($this->configPath)) {
            $this->logger->error('export.config.missing', ['path' => $this->configPath]);
            throw new \RuntimeException('Export configuration file not found: ' . $this->configPath);
        }

        $configContent = file_get_contents($this->configPath);
        if ($configContent === false) {
            $this->logger->error('export.config.read_failed', ['path' => $this->configPath]);
            throw new \RuntimeException('Failed to read export configuration file');
        }

        $this->config = json_decode($configContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('export.config.invalid_json', [
                'path' => $this->configPath,
                'error' => json_last_error_msg()
            ]);
            throw new \RuntimeException('Invalid JSON in export configuration file');
        }

        $this->logger->info('export.config.loaded', ['path' => $this->configPath]);
    }

    /**
     * Export data to Excel with config-driven approach
     */
    public function exportSabt(array $rows): string
    {
        $startTime = microtime(true);
        $this->logger->info('export.start', ['rows_count' => count($rows)]);

        try {
            $spreadsheet = new Spreadsheet();
            
            // Process each sheet from configuration
            foreach ($this->config['sheets'] ?? [] as $sheetName => $sheetConfig) {
                $this->processSheet($spreadsheet, $sheetName, $sheetConfig, $rows);
            }

            // Create Summary sheet (always present)
            $this->createSummarySheet($spreadsheet, $rows);

            // Create Errors sheet for any export errors
            $this->createErrorsSheet($spreadsheet);

            // Generate filename
            $filename = $this->generateFilename();
            $filePath = $this->getExportPath($filename);

            // Write file
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            $duration = (int) round((microtime(true) - $startTime) * 1000);
            
            // Log successful export
            $this->logExportSuccess($filename, count($rows), 0, $duration);
            
            // Update metrics
            $this->metrics->inc('export_success_total');
            $this->metrics->observe('export_duration_ms_sum', $duration);

            $this->logger->info('export.success', [
                'filename' => $filename,
                'rows_count' => count($rows),
                'duration_ms' => $duration
            ]);

            return $filePath;

        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $startTime) * 1000);
            
            // Log export failure
            $this->logExportError($e->getMessage(), $duration);
            
            // Update metrics
            $this->metrics->inc('export_failed_total');
            $this->metrics->observe('export_duration_ms_sum', $duration);

            $this->logger->error('export.failed', [
                'error' => $e->getMessage(),
                'duration_ms' => $duration
            ]);

            throw $e;
        }
    }

    /**
     * Process a single sheet based on configuration
     */
    private function processSheet(Spreadsheet $spreadsheet, string $sheetName, array $sheetConfig, array $rows): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        if ($sheetName !== 'Sheet1') {
            $sheet = $spreadsheet->createSheet();
        }
        
        $sheet->setTitle($sheetName);

        // Normalize sheets from dictionary to array
        $sheetData = $this->normalizeSheetData($sheetConfig, $rows);
        
        // Set headers
        $this->setSheetHeaders($sheet, $sheetConfig['columns'] ?? []);
        
        // Populate data
        $this->populateSheetData($sheet, $sheetData, $sheetConfig['columns'] ?? []);
    }

    /**
     * Normalize sheet data from dictionary to array
     */
    private function normalizeSheetData(array $sheetConfig, array $rows): array
    {
        $normalized = [];
        
        foreach ($rows as $rowIndex => $row) {
            $normalizedRow = [];
            
            foreach ($sheetConfig['columns'] ?? [] as $columnKey => $columnConfig) {
                $value = $this->extractColumnValue($row, $columnConfig);
                $normalizedRow[$columnKey] = $this->normalizeValue($value, $columnConfig);
            }
            
            $normalized[] = $normalizedRow;
        }
        
        return $normalized;
    }

    /**
     * Extract value for a column based on configuration
     */
    private function extractColumnValue(array $row, array $columnConfig): mixed
    {
        $source = $columnConfig['source'] ?? 'empty';
        
        switch ($source) {
            case 'gf':
                $fieldId = $columnConfig['field_id'] ?? '';
                return $row[$fieldId] ?? '';
                
            case 'db':
                $fieldName = $columnConfig['field_name'] ?? '';
                return $row[$fieldName] ?? '';
                
            case 'empty':
                return '';
                
            case 'derived':
                return $this->calculateDerivedValue($row, $columnConfig);
                
            case 'system':
                return $this->getSystemValue($columnConfig);
                
            default:
                return '';
        }
    }

    /**
     * Normalize value based on configuration rules
     */
    private function normalizeValue(mixed $value, array $columnConfig): mixed
    {
        $normalize = $columnConfig['normalize'] ?? [];
        
        foreach ($normalize as $rule) {
            switch ($rule) {
                case 'digits_10':
                    $value = $this->normalizeDigits($value);
                    break;
                    
                case 'mobile_ir':
                    $value = $this->normalizeMobileIran($value);
                    break;
                    
                case 'text_or_empty':
                    $value = empty($value) ? '' : (string) $value;
                    break;
                    
                case 'trim':
                    $value = trim((string) $value);
                    break;
            }
        }
        
        return $value;
    }

    /**
     * Set sheet headers
     */
    private function setSheetHeaders($sheet, array $columns): void
    {
        $colIndex = 1;
        foreach ($columns as $columnKey => $columnConfig) {
            $header = $columnConfig['title'] ?? $columnKey;
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $header);
            $colIndex++;
        }
    }

    /**
     * Populate sheet data
     */
    private function populateSheetData($sheet, array $data, array $columns): void
    {
        $rowIndex = 2; // Start after headers
        
        foreach ($data as $dataRow) {
            $colIndex = 1;
            
            foreach ($columns as $columnKey => $columnConfig) {
                $value = $dataRow[$columnKey] ?? '';
                
                // Use setCellValueExplicit for leading zeros
                if ($this->shouldUseExplicitValue($columnConfig)) {
                    $sheet->setCellValueExplicit(
                        $sheet->getCellByColumnAndRow($colIndex, $rowIndex),
                        $value,
                        DataType::TYPE_STRING
                    );
                } else {
                    $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $value);
                }
                
                $colIndex++;
            }
            
            $rowIndex++;
        }
    }

    /**
     * Create Summary sheet (always present)
     */
    private function createSummarySheet(Spreadsheet $spreadsheet, array $rows): void
    {
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Summary');

        // Get export statistics
        $totalRows = count($rows);
        $failedRows = count($this->getExportErrors());
        $successRows = $totalRows - $failedRows;
        $exportTime = current_time('Y-m-d H:i:s');
        $pluginVersion = defined('SMARTALLOC_VERSION') ? SMARTALLOC_VERSION : '1.1.2';

        // Set summary data
        $summaryData = [
            ['Metric', 'Value'],
            ['Total Rows Processed', $totalRows],
            ['Successful Rows', $successRows],
            ['Failed Rows', $failedRows],
            ['Success Rate', $totalRows > 0 ? round(($successRows / $totalRows) * 100, 2) . '%' : '0%'],
            ['Export Date', $exportTime],
            ['Plugin Version', $pluginVersion],
            ['Export Type', 'ALLOCATED'],
            ['File Format', 'XLSX'],
            ['Generated By', 'SmartAlloc WordPress Plugin']
        ];

        // Populate summary sheet
        foreach ($summaryData as $rowIndex => $rowData) {
            $colIndex = 1;
            foreach ($rowData as $cellValue) {
                $summarySheet->setCellValueByColumnAndRow($colIndex, $rowIndex + 1, $cellValue);
                $colIndex++;
            }
        }

        // Style the header row
        $summarySheet->getStyle('A1:B1')->getFont()->setBold(true);
        $summarySheet->getColumnDimension('A')->setAutoSize(true);
        $summarySheet->getColumnDimension('B')->setAutoSize(true);
    }

    /**
     * Create Errors sheet
     */
    private function createErrorsSheet(Spreadsheet $spreadsheet): void
    {
        $errorsSheet = $spreadsheet->createSheet();
        $errorsSheet->setTitle('Errors');
        
        // Set headers
        $errorsSheet->setCellValue('A1', 'Row');
        $errorsSheet->setCellValue('B1', 'Column');
        $errorsSheet->setCellValue('C1', 'Error');
        $errorsSheet->setCellValue('D1', 'Details');
        
        // Get errors from database
        $errors = $this->getExportErrors();
        
        $rowIndex = 2;
        foreach ($errors as $error) {
            $errorsSheet->setCellValue("A{$rowIndex}", $error['row_idx']);
            $errorsSheet->setCellValue("B{$rowIndex}", $error['sheet']);
            $errorsSheet->setCellValue("C{$rowIndex}", $error['error_code']);
            $errorsSheet->setCellValue("D{$rowIndex}", $error['error_detail']);
            $rowIndex++;
        }
    }

    /**
     * Generate export filename
     */
    private function generateFilename(): string
    {
        $prefix = $this->config['filename_prefix'] ?? 'SabtExport';
        $date = current_time('Y_m_d');
        $counter = $this->getNextExportCounter();
        $batchNumber = $this->getNextBatchNumber();
        
        return sprintf('%s-ALLOCATED-%s-%04d-B%03d.xlsx', $prefix, $date, $counter, $batchNumber);
    }

    /**
     * Get next batch number for the day
     */
    private function getNextBatchNumber(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_counters';
        $today = current_time('Y-m-d');
        
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}(scope, val) VALUES(%s, 1) 
             ON DUPLICATE KEY UPDATE val = LAST_INSERT_ID(val + 1)",
            'batch_counter_' . $today
        ));
        
        return (int) $wpdb->insert_id;
    }

    /**
     * Get export file path
     */
    private function getExportPath(string $filename): string
    {
        $uploadDir = wp_upload_dir();
        $exportDir = trailingslashit($uploadDir['basedir']) . 'smart-alloc/exports/';
        
        if (!wp_mkdir_p($exportDir)) {
            throw new \RuntimeException('Failed to create export directory: ' . $exportDir);
        }
        
        return $exportDir . $filename;
    }

    /**
     * Get next export counter
     */
    private function getNextExportCounter(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_counters';
        
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}(scope, val) VALUES(%s, 1) 
             ON DUPLICATE KEY UPDATE val = LAST_INSERT_ID(val + 1)",
            'export_counter'
        ));
        
        return (int) $wpdb->insert_id;
    }

    /**
     * Log successful export
     */
    private function logExportSuccess(string $filename, int $rowsTotal, int $rowsFailed, int $duration): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_export_log';
        
        $wpdb->insert($table, [
            'file_name' => $filename,
            'rows_total' => $rowsTotal,
            'rows_failed' => $rowsFailed,
            'duration_ms' => $duration,
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Log export error
     */
    private function logExportError(string $error, int $duration): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_export_errors';
        
        $wpdb->insert($table, [
            'export_id' => 0, // No specific export ID for failed exports
            'row_idx' => 0,
            'sheet' => 'general',
            'error_code' => 'export_failed',
            'error_detail' => $error
        ]);
    }

    /**
     * Get export errors from database
     */
    private function getExportErrors(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_export_errors';
        
        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT 100",
            'ARRAY_A'
        ) ?: [];
    }

    /**
     * Check if should use explicit cell value
     */
    private function shouldUseExplicitValue(array $columnConfig): bool
    {
        $dataType = $columnConfig['data_type'] ?? '';
        return in_array($dataType, ['string', 'text']) || 
               isset($columnConfig['leading_zeros']) || 
               in_array('digits_10', $columnConfig['normalize'] ?? []);
    }

    /**
     * Normalize digits (Persian/Arabic to English)
     */
    private function normalizeDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        $value = str_replace($persian, $english, $value);
        $value = str_replace($arabic, $english, $value);
        
        return preg_replace('/\D/', '', $value);
    }

    /**
     * Normalize Iranian mobile number
     */
    private function normalizeMobileIran(string $value): string
    {
        $value = $this->normalizeDigits($value);
        
        // Remove country code if present
        if (str_starts_with($value, '98')) {
            $value = substr($value, 2);
        }
        
        // Ensure it starts with 09
        if (!str_starts_with($value, '09')) {
            $value = '09' . $value;
        }
        
        return $value;
    }

    /**
     * Calculate derived value
     */
    private function calculateDerivedValue(array $row, array $columnConfig): mixed
    {
        $formula = $columnConfig['formula'] ?? '';
        
        switch ($formula) {
            case 'full_name':
                $firstName = $row['first_name'] ?? '';
                $lastName = $row['last_name'] ?? '';
                return trim($firstName . ' ' . $lastName);
                
            case 'age':
                $birthYear = $row['birth_year'] ?? '';
                if ($birthYear) {
                    return date('Y') - (int) $birthYear;
                }
                return '';
                
            default:
                return '';
        }
    }

    /**
     * Get system value
     */
    private function getSystemValue(array $columnConfig): mixed
    {
        $type = $columnConfig['system_type'] ?? '';
        
        switch ($type) {
            case 'current_date':
                return current_time('Y-m-d');
                
            case 'current_time':
                return current_time('H:i:s');
                
            case 'export_timestamp':
                return current_time('Y-m-d H:i:s');
                
            default:
                return '';
        }
    }
} 
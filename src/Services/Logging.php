<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Infra\Logging\Redactor;

/**
 * Logging service with data masking for sensitive information
 */
final class Logging implements LoggerInterface
{
    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Log an informational message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log a warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Log an error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Internal logging method with data masking and rotation
     */
    private function log(string $level, string $message, array $context): void
    {
        $maskedContext = $this->maskSensitiveData($context);
        $logMessage = sprintf('[SmartAlloc][%s] %s %s', $level, $message, wp_json_encode($maskedContext));
        
        // Check if we should use file logging
        $logFile = $this->getLogFile();
        if ($logFile) {
            $this->writeToFile($logFile, $logMessage);
        } else {
            error_log($logMessage);
        }
    }

    /**
     * Mask sensitive data in context arrays
     */
    private function maskSensitiveData(array $context): array
    {
        $redactor = new Redactor();
        return $redactor->redact($context);
    }

    /**
     * Get log file path
     */
    private function getLogFile(): ?string
    {
        $logPath = apply_filters('smartalloc_log_path', null);
        if ($logPath) {
            return $logPath;
        }

        // Default log path in uploads directory
        $uploadDir = wp_upload_dir();
        $logDir = trailingslashit($uploadDir['basedir']) . 'smart-alloc/logs/';
        
        if (!wp_mkdir_p($logDir)) {
            return null;
        }

        return $logDir . 'smartalloc-' . date('Y-m-d') . '.log';
    }

    /**
     * Write log message to file with rotation
     */
    private function writeToFile(string $logFile, string $message): void
    {
        try {
            // Check file size for rotation (default: 10MB)
            $maxSize = apply_filters('smartalloc_log_max_size', 10 * 1024 * 1024);
            
            if (file_exists($logFile) && filesize($logFile) > $maxSize) {
                $this->rotateLogFile($logFile);
            }

            $timestamp = current_time('Y-m-d H:i:s');
            $formattedMessage = "[{$timestamp}] {$message}" . PHP_EOL;
            
            file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
            
        } catch (\Throwable $e) {
            // Fallback to error_log if file writing fails
            error_log("SmartAlloc log file error: " . $e->getMessage());
            error_log($message);
        }
    }

    /**
     * Rotate log file
     */
    private function rotateLogFile(string $logFile): void
    {
        $backupFile = $logFile . '.backup';
        
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
        
        rename($logFile, $backupFile);
    }

    /**
     * Get log file contents
     */
    public function getLogContents(string $date = null): string
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        $logFile = $this->getLogFile();
        if (!$logFile || !file_exists($logFile)) {
            return '';
        }

        return file_get_contents($logFile) ?: '';
    }

    /**
     * Clear log file
     */
    public function clearLog(): bool
    {
        $logFile = $this->getLogFile();
        if (!$logFile) {
            return false;
        }

        try {
            file_put_contents($logFile, '');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get log file info
     */
    public function getLogInfo(): array
    {
        $logFile = $this->getLogFile();
        
        if (!$logFile || !file_exists($logFile)) {
            return [
                'exists' => false,
                'size' => 0,
                'last_modified' => null
            ];
        }

        return [
            'exists' => true,
            'size' => filesize($logFile),
            'last_modified' => filemtime($logFile),
            'path' => $logFile
        ];
    }
} 
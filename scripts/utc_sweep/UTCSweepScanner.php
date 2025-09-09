<?php

/**
 * UTC Sweep Scanner - Identifies current_time('mysql') usage.
 *
 * @package SmartAlloc
 * @subpackage UTC_Migration
 */

namespace SmartAlloc\UTC;

class UTCSweepScanner
{
    private array $candidates = [];

    private array $write_patterns = [
        'insert',
        'update',
        'wpdb->insert',
        'wpdb->update',
        'log',
        'audit',
        'export',
        '->finish',
        '->start',
        'save',
        'store',
        'record',
        'track',
    ];

    public function scan(string $path): array
    {
        $files = [];
        if (is_file($path)) {
            $files[] = $path;
        } else {
            $files = $this->getPhpFiles($path);
        }

        foreach ($files as $file) {
            $this->scanFile($file);
        }

        return [
            'summary' => [
                'total'   => count($this->candidates),
                'reads'   => $this->countByKind('read'),
                'writes'  => $this->countByKind('write'),
            ],
            'items'   => $this->candidates,
        ];
    }

    private function scanFile(string $filepath): void
    {
        $content = file_get_contents($filepath);
        $lines   = explode("\n", $content);

        foreach ($lines as $line_num => $line) {
            if ($this->hasCurrentTimeMysql($line)) {
                $this->candidates[] = [
                    'file'   => $filepath,
                    'line'   => $line_num + 1,
                    'code'   => trim($line),
                    'kind'   => $this->classifyUsage($line),
                    'reason' => $this->getClassificationReason($line),
                ];
            }
        }
    }

    public function hasCurrentTimeMysql(string $line): bool
    {
        // Match with flexible whitespace.
        return preg_match('/current_time\s*\(\s*[\'\"]mysql[\'\"]\s*\)/', $line) === 1;
    }

    private function classifyUsage(string $line): string
    {
        $line_lower = strtolower($line);

        foreach ($this->write_patterns as $pattern) {
            if (strpos($line_lower, $pattern) !== false) {
                return 'write';
            }
        }

        return 'read';
    }

    private function getClassificationReason(string $line): string
    {
        $line_lower = strtolower($line);

        foreach ($this->write_patterns as $pattern) {
            if (strpos($line_lower, $pattern) !== false) {
                return "Contains {$pattern} pattern";
            }
        }

        return 'Default read classification';
    }

    private function countByKind(string $kind): int
    {
        return count(
            array_filter(
                $this->candidates,
                fn($c) => $c['kind'] === $kind
            )
        );
    }

    private function getPhpFiles(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $files    = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}

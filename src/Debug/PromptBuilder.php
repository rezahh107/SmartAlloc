<?php

declare(strict_types=1);

namespace SmartAlloc\Debug;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Build copyable markdown prompt from stored error data.
 */
final class PromptBuilder
{
    /**
     * @param array<string,mixed> $entry
     */
    public function build(array $entry): string
    {
        $lines = [];
        $lines[] = '# Summary';
        $lines[] = $entry['message'] ?? 'Unknown error';
        $lines[] = '';
        $lines[] = '## Error';
        $lines[] = sprintf('%s:%s', (string) ($entry['file'] ?? ''), (string) ($entry['line'] ?? '')); // @phpstan-ignore-line
        $lines[] = '';
        $lines[] = '## Stack';
        $stack = is_array($entry['stack'] ?? null) ? $entry['stack'] : [];
        foreach ($stack as $s) {
            $lines[] = '- ' . $s;
        }
        $lines[] = '';
        $lines[] = '## Context';
        $ctx = is_array($entry['context'] ?? null) ? $entry['context'] : [];
        foreach ($ctx as $k => $v) {
            /** @phpstan-ignore-next-line */
            $lines[] = sprintf('%s: %s', (string) $k, is_scalar($v) ? (string) $v : wp_json_encode($v));
        }
        $lines[] = '';
        $lines[] = '## Environment';
        $env = is_array($entry['env'] ?? null) ? $entry['env'] : [];
        foreach ($env as $k => $v) {
            /** @phpstan-ignore-next-line */
            $lines[] = sprintf('%s: %s', (string) $k, is_scalar($v) ? (string) $v : wp_json_encode($v));
        }
        $lines[] = '';
        $logs = is_array($entry['logs'] ?? null) ? $entry['logs'] : [];
        if (!empty($logs)) {
            $lines[] = '## Recent Logs';
            foreach ($logs as $log) {
                /** @phpstan-ignore-next-line */
                $lines[] = '- ' . wp_json_encode($log);
            }
            $lines[] = '';
        }
        $queries = is_array($entry['queries'] ?? null) ? $entry['queries'] : [];
        if (!empty($queries)) {
            $lines[] = '## Recent Queries';
            foreach ($queries as $q) {
                $lines[] = '- ' . $q;
            }
            $lines[] = '';
        }
        if (isset($entry['file'], $entry['line']) && is_string($entry['file'])) {
            /** @phpstan-ignore-next-line */
            $snippet = $this->snippet($entry['file'], (int) ($entry['line'] ?? 0));
            if ($snippet !== '') {
                $lines[] = '## Code Snippets';
                $lines[] = "```php\n" . $snippet . "\n```";
                $lines[] = '';
            }
        }
        $lines[] = '## Acceptance & Constraints';
        $lines[] = 'No secrets. Output must be markdown.';
        $lines[] = '';
        $tz = new DateTimeZone('UTC');
        $lines[] = (new DateTimeImmutable('now', $tz))->format(DateTimeImmutable::ATOM);
        return implode("\n", $lines);
    }

    private function snippet(string $file, int $line): string
    {
        if (!is_readable($file)) {
            return '';
        }
        $start = max($line - 20, 1);
        $end = $line + 20;
        $src = file($file) ?: [];
        $out = '';
        for ($i = $start; $i <= $end && $i <= count($src); $i++) {
            $out .= $i . ': ' . rtrim($src[$i - 1]) . "\n";
        }
        return trim($out);
    }
}

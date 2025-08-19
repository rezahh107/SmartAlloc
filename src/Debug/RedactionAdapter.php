<?php

declare(strict_types=1);

namespace SmartAlloc\Debug;

use SmartAlloc\Infra\Logging\Redactor as BaseRedactor;

/**
 * Adapter around existing redactor to scrub additional fields.
 */
final class RedactionAdapter
{
    private BaseRedactor $redactor;

    public function __construct(?BaseRedactor $redactor = null)
    {
        $this->redactor = $redactor ?? new BaseRedactor();
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function redact(array $context): array
    {
        if (isset($context['context']) && is_array($context['context'])) {
            $context['context'] = $this->redactor->redact($context['context']);
            if (isset($context['context']['route']) && is_string($context['context']['route'])) {
                $context['context']['route'] = $this->stripQuery($context['context']['route']);
            }
        }
        if (isset($context['file']) && is_string($context['file'])) {
            $context['file'] = $this->shortenPath($context['file']);
        }
        foreach ($context as $k => $v) {
            if (is_string($v)) {
                $context[$k] = $this->removeEmails($v);
            }
        }
        return $context;
    }

    private function stripQuery(string $url): string
    {
        /** @phpstan-ignore-next-line */
        $parts = wp_parse_url($url);
        if (!$parts) {
            return $url;
        }
        unset($parts['query']);
        return $this->unparseUrl($parts);
    }

    /**
     * @param array<string,string|int> $parts
     */
    private function unparseUrl(array $parts): string
    {
        $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host     = $parts['host'] ?? '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path     = $parts['path'] ?? '';
        return $scheme . $host . $port . $path;
    }

    private function shortenPath(string $path): string
    {
        $root = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '';
        if ($root !== '' && str_starts_with($path, $root)) {
            return str_replace($root, '', $path);
        }
        return $path;
    }

    private function removeEmails(string $value): string
    {
        return (string) preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted]', $value);
    }
}

<?php

declare(strict_types=1);

namespace SmartAlloc\Logging;

use SmartAlloc\Infra\Logging\Redactor;

/**
 * Simple structured logger with level filtering and PII masking.
 * Stores records in-memory for tests.
 */
final class Logger
{
    public const DEBUG = 0;
    public const INFO = 1;
    public const WARN = 2;
    public const ERROR = 3;

    private int $level;
    private Redactor $redactor;

    /** @var array<int,array<string,mixed>> */
    public array $records = [];

    public function __construct(int $level = self::INFO, ?Redactor $redactor = null)
    {
        $this->level = $level;
        $this->redactor = $redactor ?? new Redactor();
    }

    /** @param array<string,mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function warn(string $message, array $context = []): void
    {
        $this->log(self::WARN, $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function log(int $level, string $message, array $context): void
    {
        if ($level < $this->level) {
            return;
        }
        $context = $this->redactor->redact($context);
        $names = [self::DEBUG => 'debug', self::INFO => 'info', self::WARN => 'warn', self::ERROR => 'error'];
        $this->records[] = [
            'level' => $names[$level] ?? (string) $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}

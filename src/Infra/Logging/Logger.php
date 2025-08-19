<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * PSR-3 style logger that redacts context and adds correlation ids.
 */
final class Logger implements LoggerInterface
{
    use LoggerTrait;

    /** @var callable */
    private $writer;
    private Redactor $redactor;

    /** @var array<int,array<string,mixed>> Records for testing */
    public array $records = [];

    private static ?string $requestId = null;

    public function __construct(?callable $writer = null, ?Redactor $redactor = null)
    {
        $this->writer = $writer ?? 'error_log';
        $this->redactor = $redactor ?? new Redactor();
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array<string,mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $context = $this->redactor->redact($context);
        $level = strtoupper((string) $level);
        $record = ['level' => $level, 'message' => $message, 'context' => $context];
        $this->records[] = $record;
        ($this->writer)(sprintf('[SmartAlloc][%s] %s %s', $level, $message, wp_json_encode($context)));
    }

    /**
     * Return per-request correlation id.
     */
    public static function requestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = bin2hex(random_bytes(8));
        }
        return self::$requestId;
    }
}

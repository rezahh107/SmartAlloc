<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Log;

use function esc_html;
use function wp_json_encode;

/**
 * WordPress based logger with basic redaction and request correlation.
 */
final class LoggerWp implements Logger
{
    /** @var callable */
    private $writer;

    /** @var array<int,array<string,mixed>> */
    public array $records = [];

    private static ?string $requestId = null;

    public function __construct(?callable $writer = null)
    {
        $this->writer = $writer ?? 'error_log';
    }

    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $context = $this->sanitizeContext($context);
        $record = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
        $this->records[] = $record;
        ($this->writer)(sprintf('[SmartAlloc][%s] %s %s', $level, $message, wp_json_encode($context)));
    }

    /** @param array<string,mixed> $context */
    public function debug(string $message, array $context = []): void
    { $this->log(self::DEBUG, $message, $context); }
    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void
    { $this->log(self::INFO, $message, $context); }
    /** @param array<string,mixed> $context */
    public function warn(string $message, array $context = []): void
    { $this->log(self::WARN, $message, $context); }
    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void
    { $this->log(self::ERROR, $message, $context); }

    /**
     * Generate/return request id.
     */
    public static function requestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = bin2hex(random_bytes(8));
        }
        return self::$requestId;
    }

    /**
     * Only keep allowlisted keys and redact sensitive values.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $allowed = ['entry_id','mentor_id','reviewer_id','status','count','counts','duration_ms','timing'];
        $out = [];
        foreach ($context as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $out[$k] = $v;
                continue;
            }
            if (in_array($k, ['mobile','national_id','postal_code'], true)) {
                $out[$k] = $this->mask((string)$v);
            }
        }
        $out['request_id'] = self::requestId();
        return $out;
    }

    private function mask(string $value): string
    {
        $len = strlen($value);
        if ($len <= 3) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, 3) . str_repeat('*', $len - 3);
    }
}

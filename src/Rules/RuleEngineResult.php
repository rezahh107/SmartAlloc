<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Rules;

final class RuleEngineResult
{
    public const OK = 'OK';
    public const RETRYABLE = 'RETRYABLE';
    public const FAIL = 'FAIL';

    public function __construct(
        public string $status,
        public string $code = 'OK',
        public array $details = []
    ) {
    }

    public function isOk(): bool
    {
        return $this->status === self::OK;
    }
}

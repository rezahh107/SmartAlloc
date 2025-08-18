<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Logger;

use SmartAlloc\Contracts\LoggerInterface;

/**
 * No-op logger used for tests and actions where logging isn't critical.
 */
final class NullLogger implements LoggerInterface
{
    public function debug(string $message, array $context = []): void {}
    public function info(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void {}
}


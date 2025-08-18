<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Log;

/**
 * Simple logger interface with four severity levels.
 */
interface Logger
{
    public const DEBUG = 'DEBUG';
    public const INFO  = 'INFO';
    public const WARN  = 'WARN';
    public const ERROR = 'ERROR';

    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function debug(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function warn(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void;
}

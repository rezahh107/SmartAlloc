<?php

declare(strict_types=1);

namespace SmartAlloc\Observability;

/**
 * Minimal in-memory tracer for test-mode spans.
 */
final class Tracer
{
    /** @var array<string,array<int,array{start:float,end:float}>> */
    private static array $spans = [];

    public static function start(string $name): void
    {
        self::$spans[$name] ??= [];
        self::$spans[$name][] = ['start' => microtime(true), 'end' => 0.0];
    }

    public static function finish(string $name): void
    {
        $idx = count(self::$spans[$name] ?? []) - 1;
        if ($idx >= 0) {
            self::$spans[$name][$idx]['end'] = microtime(true);
        }
    }

    /**
     * @return array<string,array<int,array{start:float,end:float}>>
     */
    public static function spans(): array
    {
        return self::$spans;
    }

    public static function reset(): void
    {
        self::$spans = [];
    }
}

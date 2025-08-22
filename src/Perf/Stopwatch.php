<?php

declare(strict_types=1);

namespace SmartAlloc\Perf;

/**
 * Lightweight stopwatch utility using hrtime().
 */
final class Stopwatch
{
    /**
     * Measure the execution time of a callable.
     *
     * @template T
     * @param callable():T $fn
     * @return PerfResult<T>
     */
    public static function measure(callable $fn): PerfResult
    {
        $start = hrtime(true);
        $return = $fn();
        $end = hrtime(true);

        return new PerfResult(($end - $start) / 1_000_000, $return);
    }
}

/**
 * Result wrapper for Stopwatch::measure.
 *
 * @template T
 */
final class PerfResult
{
    /**
     * @param float $durationMs Duration in milliseconds
     * @param T $result Return value from the measured callable
     */
    public function __construct(
        public readonly float $durationMs,
        public readonly mixed $result,
    ) {
    }
}


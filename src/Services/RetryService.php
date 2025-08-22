<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Exponential backoff with jitter helper.
 */
final class RetryService
{
    public function __construct(private int $maxDelay = 60, private int $jitter = 3)
    {
    }

    /**
     * Calculate delay seconds for given attempt (1-based).
     */
    public function backoff(int $attempt): int
    {
        $base = (int) pow(2, max(0, $attempt - 1));
        $base = min($this->maxDelay, $base);
        $jitter = mt_rand(0, $this->jitter);
        return $base + $jitter;
    }
}

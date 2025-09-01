<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\TestDoubles;

use SmartAlloc\Services\Metrics;

final class NullMetrics extends Metrics
{
    public function __construct() {}

    public function inc(string $key, float $value = 1.0, array $labels = []): void {}

    public function observe(string $key, int $milliseconds, array $labels = []): void {}
}


<?php

declare(strict_types=1);

namespace SmartAlloc\Domain\Export;

use SmartAlloc\Infra\Metrics\MetricsCollector;

/**
 * Simple time-window based circuit breaker for exports.
 */
final class CircuitBreaker
{
    private const OPTION_KEY = 'smartalloc_export_cb';
    private const FAILURE_WINDOW = 120; // seconds
    private const OPEN_DURATION  = 300; // seconds

    public function __construct(private MetricsCollector $metrics)
    {
    }

    /**
     * Whether exports are allowed at this time.
     */
    public function allow(): bool
    {
        $data = $this->read();
        if ($data['state'] === 'open') {
            if (time() - $data['opened_at'] >= self::OPEN_DURATION) {
                $data['state'] = 'half';
                $this->write($data);
                return true;
            }
            return false;
        }
        return true;
    }

    public function getState(): string
    {
        return $this->read()['state'];
    }

    public function getRetryAfter(): int
    {
        $data = $this->read();
        if ($data['state'] !== 'open') {
            return 0;
        }
        $elapsed = time() - $data['opened_at'];
        $remain  = self::OPEN_DURATION - $elapsed;
        return $remain > 0 ? $remain : 0;
    }

    public function recordSuccess(): void
    {
        $this->write(['state' => 'closed', 'failures' => [], 'opened_at' => 0]);
    }

    public function recordFailure(): void
    {
        $data = $this->read();
        $now  = time();
        $data['failures'] = array_filter(
            $data['failures'],
            static fn(int $ts): bool => $ts >= $now - self::FAILURE_WINDOW
        );
        $data['failures'][] = $now;
        if (count($data['failures']) >= 5) {
            if ($data['state'] !== 'open') {
                $data['state']  = 'open';
                $data['opened_at'] = $now;
                $this->metrics->inc('breaker_open');
                error_log('export.circuit.open');
            }
        }
        $this->write($data);
    }

    private function read(): array
    {
        $data = get_option(self::OPTION_KEY, []);
        return array_merge(['state' => 'closed', 'failures' => [], 'opened_at' => 0], is_array($data) ? $data : []);
    }

    private function write(array $data): void
    {
        update_option(self::OPTION_KEY, $data, false);
    }
}

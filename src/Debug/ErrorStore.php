<?php

declare(strict_types=1);

namespace SmartAlloc\Debug;

use SmartAlloc\Infra\Metrics\MetricsCollector;

/**
 * Ring buffer store for captured errors.
 */
final class ErrorStore
{
    private const OPTION = 'smartalloc_debug_errors';
    private const MAX_ENTRIES = 25;
    private const MAX_SIZE = 120000; // ~120KB

    /**
     * Add a new entry to store.
     *
     * @param array<string,mixed> $entry
     */
    public static function add(array $entry): void
    {
        $data = self::all();
        /** @phpstan-ignore-next-line */
        $payload = wp_json_encode($entry) ?: '';
        if (strlen($payload) > self::MAX_SIZE) {
            $entry = [
                'truncated' => true,
                'message' => 'Entry exceeded size limit',
            ];
        }
        array_unshift($data, $entry);
        $data = array_slice($data, 0, self::MAX_ENTRIES);
        /** @phpstan-ignore-next-line */
        update_option(self::OPTION, $data, false);

        $metrics = new MetricsCollector();
        $current = $metrics->all()['gauges']['debug_error_store_size'] ?? 0;
        $metrics->gauge('debug_error_store_size', count($data) - (int) $current);
    }

    /**
     * Retrieve all entries.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        $data = get_option(self::OPTION);
        if (!is_array($data)) {
            return [];
        }
        return $data;
    }
}

<?php

declare(strict_types=1);

namespace SmartAlloc\Perf;

/**
 * Simple query counter for tests. Wraps global $wpdb->queries if available.
 */
final class QueryCounter
{
    private int $start = 0;

    public function start(): void
    {
        global $wpdb;
        if (is_object($wpdb) && is_array($wpdb->queries ?? null)) {
            $this->start = count($wpdb->queries);
        } else {
            $this->start = 0;
        }
    }

    /**
     * @return int Number of queries executed since start()
     */
    public function stop(): int
    {
        global $wpdb;
        if (!is_object($wpdb) || !is_array($wpdb->queries ?? null)) {
            return 0;
        }

        return max(0, count($wpdb->queries) - $this->start);
    }
}


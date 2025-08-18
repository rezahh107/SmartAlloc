<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Export;

use InvalidArgumentException;

/**
 * Exporter using WordPress database access with table name safety.
 *
 * @phpcs:ignoreFile
 */
class ExporterService
{
    public function __construct(private $wpdb = null)
    {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function exportData(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid id');
        }

        $table = $this->wpdb->prefix . 'exports';
        $sql   = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", absint($id));

        /** @var list<array<string,mixed>> $results */
        $results = $this->wpdb->get_results($sql, 'ARRAY_A') ?: [];

        return $results;
    }
}

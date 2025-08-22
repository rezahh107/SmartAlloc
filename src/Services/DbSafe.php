<?php
declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Helper ensuring SQL statements are fully prepared.
 */
final class DbSafe
{
    /**
     * Prepare SQL with strict placeholder enforcement.
     *
     * @param array<int|string|float|null> $params
     * @throws \InvalidArgumentException when placeholder count mismatches
     * @throws \RuntimeException when placeholders remain after preparing
     */
    public static function mustPrepare(string $sql, array $params): string
    {
        $expected = preg_match_all('/(?<!%)%[dsf]/i', $sql);
        if ($expected !== count($params)) {
            throw new \InvalidArgumentException('SQL placeholder count mismatch');
        }

        global $wpdb;
        $prepared = $wpdb->prepare($sql, $params);
        if (preg_match('/(?<!%)%[dsf]/i', $prepared)) {
            throw new \RuntimeException('SQL not fully prepared');
        }
        return $prepared;
    }
}

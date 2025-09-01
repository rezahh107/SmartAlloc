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
        $fault = false;
        if (function_exists('apply_filters')) {
            $fault = (bool) apply_filters('smartalloc_test_fault_db_down', false);
            if (!$fault && isset($GLOBALS['filters']['smartalloc_test_fault_db_down'])) {
                $fault = (bool) $GLOBALS['filters']['smartalloc_test_fault_db_down'](false);
            }
        }
        if ($fault) {
            throw new \RuntimeException('database unavailable');
        }
        $expected = preg_match_all('/(?<!%)%[dsf]/i', $sql);
        if ($expected !== count($params)) {
            throw new \InvalidArgumentException('SQL placeholder count mismatch');
        }

        global $wpdb;
        $prepared = $wpdb->prepare($sql, $params); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if (preg_match('/(?<!%)%[dsf]/i', $prepared)) {
            throw new \RuntimeException('SQL not fully prepared');
        }
        return $prepared;
    }

    /**
     * Prepare multiple value fragments for bulk inserts.
     *
     * @param array<int,array<int|string|float|null>> $rows
     * @return array<int,string>
     */
    public static function mustPrepareMany(string $fragment, array $rows): array
    {
        $prepared = [];
        foreach ($rows as $params) {
            $prepared[] = self::mustPrepare($fragment, $params);
        }
        return $prepared;
    }
}

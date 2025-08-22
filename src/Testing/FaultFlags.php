<?php

declare(strict_types=1);

namespace SmartAlloc\Testing;

/**
 * Global fault injection flags for tests only.
 */
final class FaultFlags
{
    /**
     * @return array{db_outage:bool,latency_ms:int,memory_pressure_mb:int,notify_partial_fail:bool,export_partial_fail:bool}
     */
    public static function get(): array
    {
        if (!defined('SMARTALLOC_TEST_MODE') || SMARTALLOC_TEST_MODE !== true) {
            return [];
        }

        $flags = function_exists('apply_filters') ? apply_filters('smartalloc/test/faults', []) : [];
        if (isset($GLOBALS['filters']['smartalloc/test/faults'])) {
            $flags = $GLOBALS['filters']['smartalloc/test/faults']($flags);
        }

        $defaults = [
            'db_outage' => false,
            'latency_ms' => 0,
            'memory_pressure_mb' => 0,
            'notify_partial_fail' => false,
            'export_partial_fail' => false,
        ];

        return array_merge($defaults, is_array($flags) ? $flags : []);
    }
}

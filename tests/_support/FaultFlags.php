<?php
namespace SmartAlloc\Testing\Chaos;

/**
 * @internal test-only fault injection flags
 */
final class FaultFlags
{
    public static bool $db_down = false;
    public static int $high_latency_ms = 0;
    public static int $memory_pressure = 0;
    /** @var array{notify?:bool,export?:bool} */
    public static array $partial_service_down = [];

    public static function reset(): void
    {
        self::$db_down = false;
        self::$high_latency_ms = 0;
        self::$memory_pressure = 0;
        self::$partial_service_down = [];
        self::apply();
    }

    public static function apply(): void
    {
        $GLOBALS['filters']['smartalloc_test_fault_db_down'] = fn($v = false) => self::$db_down;
        $GLOBALS['filters']['smartalloc_test_fault_latency_ms'] = fn($v = 0) => self::$high_latency_ms;
        $GLOBALS['filters']['smartalloc_test_fault_memory_pressure'] = fn($v = 0) => self::$memory_pressure;
        $GLOBALS['filters']['smartalloc_test_fault_partial_service'] = fn($v = []) => self::$partial_service_down;
    }
}

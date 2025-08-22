<?php
namespace SmartAlloc\Tests\ChaosEngineering;

use SmartAlloc\Services\DbSafe;
use SmartAlloc\Services\ErrorResponse;
use SmartAlloc\Testing\Chaos\FaultFlags;

final class ChaosTestHelpers
{
    /** @return array{error:array{code:string,message:string,details:array}}|array{ok:true} */
    public static function simulateDbQuery(): array
    {
        try {
            DbSafe::mustPrepare('SELECT 1', []);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ErrorResponse::from($e);
        }
    }

    /** simulate batch processing under memory pressure */
    public static function processBatch(int $items): int
    {
        $peak = 0;
        for ($i = 0; $i < $items; $i++) {
            if (FaultFlags::$memory_pressure > 0) {
                $junk = str_repeat('x', FaultFlags::$memory_pressure);
                unset($junk);
            }
            $peak = max($peak, memory_get_usage(true));
        }
        return $peak;
    }

    /** simulate notify call respecting fault flags */
    public static function notify(): bool
    {
        $delay = (int) apply_filters('smartalloc_test_fault_latency_ms', 0);
        if (isset($GLOBALS['filters']['smartalloc_test_fault_latency_ms'])) {
            $delay = (int) $GLOBALS['filters']['smartalloc_test_fault_latency_ms']($delay);
        }
        if ($delay > 0) {
            usleep($delay * 1000);
        }
        $partial = apply_filters('smartalloc_test_fault_partial_service', []);
        if (isset($GLOBALS['filters']['smartalloc_test_fault_partial_service'])) {
            $partial = $GLOBALS['filters']['smartalloc_test_fault_partial_service']($partial);
        }
        if (!empty($partial['notify'])) {
            throw new \RuntimeException('notify down');
        }
        return true;
    }
}

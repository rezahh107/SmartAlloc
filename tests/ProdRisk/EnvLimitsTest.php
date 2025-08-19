<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ProdRisk;

use SmartAlloc\Tests\BaseTestCase;

final class EnvLimitsTest extends BaseTestCase
{
    public function test_environment_limits_or_skip(): void
    {
        $maxInput = ini_get('max_input_vars');
        $memory = ini_get('memory_limit');
        if ($maxInput === false || $memory === false) {
            self::markTestSkipped('env-limits unknown');
        }
        $memBytes = self::toBytes((string) $memory);
        if ($memBytes > 0 && $memBytes < 8 * 1024 * 1024) {
            self::markTestSkipped('env-limits too strict');
        }
        $start = memory_get_usage(true);
        $data = range(1, 1000);
        $json = json_encode($data);
        $end = memory_get_usage(true);
        $this->assertLessThan(1_000_000, $end - $start);
        $this->assertNotEmpty($json);
    }

    private static function toBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int) $val;
        switch ($last) {
            case 'g':
                $num *= 1024;
                // no break
            case 'm':
                $num *= 1024;
                // no break
            case 'k':
                $num *= 1024;
        }
        return $num;
    }
}

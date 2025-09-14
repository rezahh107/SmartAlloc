<?php
declare(strict_types=1);

namespace {
    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data) {
            return json_encode($data);
        }
    }
}

namespace SmartAlloc\Tests\Unit\Services {

use Exception;
use PHPUnit\Framework\TestCase;
use SmartAlloc\Database\DbPort;
use SmartAlloc\Services\Metrics;

final class MetricsTest extends TestCase
{
    public function test_constructor_accepts_db_port_and_table(): void
    {
        $mockDb = $this->createMock(DbPort::class);
        $metrics = new Metrics($mockDb, 'wp_salloc_metrics');

        $this->assertInstanceOf(Metrics::class, $metrics);
    }

    public function test_database_exception_handling(): void
    {
        $mockDb = $this->createMock(DbPort::class);
        $mockDb->method('exec')->willThrowException(new Exception('DB Error'));

        $metrics = new Metrics($mockDb, 'wp_salloc_metrics');
        $result = $metrics->get('foo');

        $this->assertSame([], $result);
    }

    public function test_error_logging_on_database_failure(): void
    {
        $mockDb = $this->createMock(DbPort::class);
        $mockDb->method('exec')->willThrowException(new Exception('Insert failed'));
        $metrics = new Metrics($mockDb, 'wp_salloc_metrics');

        $temp = tempnam(sys_get_temp_dir(), 'log');
        $log = ini_set('error_log', $temp);
        $metrics->inc('foo');
        ini_set('error_log', (string) $log);
        $output = file_get_contents($temp) ?: '';
        unlink($temp);

        $this->assertStringContainsString('Metrics::inc: Insert failed', $output);
    }

}

}


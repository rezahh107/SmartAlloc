<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Database\DbPort;
use SmartAlloc\Services\Metrics;

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

final class MetricsIntegrationTest extends TestCase
{
    public function test_error_log_fallback_when_no_logger(): void
    {
        $db = new class implements DbPort {
            public function exec(string $sql, mixed ...$args)
            {
                throw new RuntimeException('fail');
            }
            public function insert_id(): int { return 0; }
        };

        ini_set('error_log', 'php://output');
        ob_start();
        $metrics = new Metrics($db, 'salloc_metrics');
        $metrics->inc('views');
        $output = ob_get_clean();

        $this->assertStringContainsString('Metrics::', (string) $output);
    }
}


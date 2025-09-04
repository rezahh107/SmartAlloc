<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use SmartAlloc\Database\DbPort;
use SmartAlloc\Services\Metrics;

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

final class MetricsTest extends TestCase
{
    public function test_constructor_injection_and_inc_uses_db_port(): void
    {
        $db = new class implements DbPort {
            public string $sql = '';
            public array $args = [];
            public function exec(string $sql, mixed ...$args)
            {
                $this->sql  = $sql;
                $this->args = $args;
                return 1;
            }
            public function insert_id(): int { return 0; }
        };

        $logger = new class extends AbstractLogger {
            /** @var array<int,array<string,mixed>> */
            public array $records = [];
            public function log($level, $message, array $context = []): void
            {
                $this->records[] = compact('level', 'message', 'context');
            }
        };

        $metrics = new Metrics($db, 'salloc_metrics', $logger);
        $metrics->inc('views');

        $this->assertStringContainsString('salloc_metrics', $db->sql);
        $this->assertEmpty($logger->records);
    }

    public function test_inc_logs_errors_and_continues(): void
    {
        $db = new class implements DbPort {
            public function exec(string $sql, mixed ...$args)
            {
                throw new RuntimeException('fail');
            }
            public function insert_id(): int { return 0; }
        };

        $logger = new class extends AbstractLogger {
            /** @var array<int,array<string,mixed>> */
            public array $records = [];
            public function log($level, $message, array $context = []): void
            {
                $this->records[] = compact('level', 'message', 'context');
            }
        };

        $metrics = new Metrics($db, 'salloc_metrics', $logger);
        $metrics->inc('views');

        $this->assertSame('error', $logger->records[0]['level']);
        $this->assertSame('Metrics database operation failed', $logger->records[0]['message']);
    }

    public function test_get_returns_safe_default_on_failure(): void
    {
        $db = new class implements DbPort {
            public function exec(string $sql, mixed ...$args)
            {
                throw new RuntimeException('fail');
            }
            public function insert_id(): int { return 0; }
        };

        $logger = new class extends AbstractLogger {
            /** @var array<int,array<string,mixed>> */
            public array $records = [];
            public function log($level, $message, array $context = []): void
            {
                $this->records[] = compact('level', 'message', 'context');
            }
        };

        $metrics = new Metrics($db, 'salloc_metrics', $logger);
        $result  = $metrics->get('views');

        $this->assertSame([], $result);
        $this->assertSame('error', $logger->records[0]['level']);
    }

    public function test_get_decodes_labels(): void
    {
        $db = new class implements DbPort {
            public function exec(string $sql, mixed ...$args)
            {
                return [
                    [
                        'metric_key' => 'views',
                        'labels'     => '{"foo":1}',
                        'value'      => 2,
                        'ts'         => '2024-01-01 00:00:00',
                    ],
                ];
            }
            public function insert_id(): int { return 0; }
        };

        $metrics = new Metrics($db, 'salloc_metrics');
        $rows    = $metrics->get('views');

        $this->assertSame(['foo' => 1], $rows[0]['labels']);
    }
}


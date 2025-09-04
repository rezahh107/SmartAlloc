<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\DlqService;
use SmartAlloc\Tests\TestDoubles\FailingDlq;
use SmartAlloc\Tests\TestDoubles\ArrayLogger;

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0) {
        return json_encode($data, $options);
    }
}

final class DlqServiceReplayLoggingTest extends BaseTestCase
{
    public function test_doReplay_logs_error_with_structured_logger(): void
    {
        $repo = new FailingDlq();
        $repo->insert('test', [], new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $logger = new ArrayLogger();

        $svc = new DlqService($repo, $logger);
        $svc->replay(1);

        $this->assertCount(1, $logger->records);
        $record = $logger->records[0];
        $this->assertSame('ERROR', $record['level']);
        $this->assertSame('DlqService::doReplay failed for row', $record['message']);
        $this->assertSame(1, $record['context']['row_id']);
        $this->assertSame('delete failed', $record['context']['exception']);
    }

    public function test_doReplay_uses_error_log_fallback(): void
    {
        $repo = new FailingDlq();
        $repo->insert('t', [], new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $tmp = tempnam(sys_get_temp_dir(), 'dlq');
        $prev = ini_set('error_log', $tmp);
        try {
            $svc = new DlqService($repo);
            $svc->replay(1);
            $content = file_get_contents($tmp) ?: '';
            $this->assertStringContainsString('DlqService::doReplay: Row ID 1 - delete failed', $content);
        } finally {
            ini_set('error_log', (string) $prev);
            @unlink($tmp);
        }
    }
}

<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\DlqService;
use SmartAlloc\Tests\TestDoubles\SpyDlq;
use SmartAlloc\Tests\TestDoubles\SpyLogger;

final class DlqServiceTest extends BaseTestCase
{
    public function testPushListGetDelete(): void
    {
        $repo = new SpyDlq();
        $svc  = new DlqService($repo);

        $svc->push([
            'event_name' => 'Evt',
            'payload'    => ['b' => 2, 'a' => 1],
            'attempts'   => 3,
            'error_text' => 'err',
        ]);

        $list = $svc->listRecent();
        $this->assertCount(1, $list);
        $this->assertSame(['a' => 1, 'b' => 2, 'attempts' => 3], $list[0]['payload']);
        $id   = $list[0]['id'];
        $item = $svc->get($id);
        $this->assertSame('Evt', $item['event_name']);
        $svc->delete($id);
        $this->assertCount(0, $svc->listRecent());
    }

    public function testLogReplayErrorWithStructuredLogger(): void
    {
        $repo   = new SpyDlq();
        $logger = new SpyLogger();
        $svc    = new DlqService($repo, $logger);

        $ref = new \ReflectionClass($svc);
        $method = $ref->getMethod('logReplayError');
        $method->setAccessible(true);
        $method->invoke($svc, new \Exception('Test error'), 123);

        $this->assertCount(1, $logger->records);
        $log = $logger->records[0];
        $this->assertSame('error', $log['level']);
        $this->assertSame('DlqService::doReplay failed for row', $log['message']);
        $this->assertSame(123, $log['context']['row_id']);
        $this->assertSame('Test error', $log['context']['exception']);
    }

    public function testLogReplayErrorUsesErrorLogFallback(): void
    {
        $repo = new SpyDlq();
        $svc  = new DlqService($repo);

        $ref = new \ReflectionClass($svc);
        $method = $ref->getMethod('logReplayError');
        $method->setAccessible(true);

        $tmp  = tempnam(sys_get_temp_dir(), 'elog');
        $prev = ini_set('error_log', $tmp);
        $method->invoke($svc, new \Exception('Fallback error'), 1);
        $output = file_get_contents($tmp) ?: '';
        if ($prev !== false) {
            ini_set('error_log', $prev);
        }
        unlink($tmp);
        $this->assertStringContainsString('DlqService::doReplay: Row ID 1 - Fallback error', $output);
    }

    public function testBackwardCompatibilityWithoutLogger(): void
    {
        $svc = new DlqService(new SpyDlq());
        $this->assertInstanceOf(DlqService::class, $svc);
    }
}

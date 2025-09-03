<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\DlqService;
use SmartAlloc\Tests\TestDoubles\SpyDlq;

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
}


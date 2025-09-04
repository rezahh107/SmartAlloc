<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\DlqService;
use SmartAlloc\Tests\TestDoubles\SpyDlq;
use SmartAlloc\Infrastructure\Contracts\DlqRepository;
use Psr\Log\LoggerInterface;

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

    public function testDoReplayLogsErrorWithStructuredLogger(): void
    {
        $repo = new class implements DlqRepository {
            public array $rows = [];
            public function insert(string $topic, array $payload, \DateTimeImmutable $createdAtUtc): bool { $this->rows[] = ['id' => count($this->rows) + 1, 'event_name' => $topic, 'payload' => $payload]; return true; }
            public function listRecent(int $limit): array { return $this->rows; }
            public function get(int $id): ?array { return null; }
            public function delete(int $id): bool { throw new \RuntimeException('Test error'); }
            public function count(): int { return count($this->rows); }
        };
        $repo->insert('Evt', ['payload' => 'x'], new \DateTimeImmutable());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'DlqService::doReplay failed for row',
            $this->callback(function ($context) {
                return ($context['row_id'] ?? null) === 1 && isset($context['exception']);
            })
        );

        $svc = new DlqService($repo, $logger);
        $svc->replay(1);
    }

    public function testDoReplayUsesErrorLogFallback(): void
    {
        $repo = new class implements DlqRepository {
            public array $rows = [];
            public function insert(string $topic, array $payload, \DateTimeImmutable $createdAtUtc): bool { $this->rows[] = ['id' => count($this->rows) + 1, 'event_name' => $topic, 'payload' => $payload]; return true; }
            public function listRecent(int $limit): array { return $this->rows; }
            public function get(int $id): ?array { return null; }
            public function delete(int $id): bool { throw new \RuntimeException('Fallback error'); }
            public function count(): int { return count($this->rows); }
        };
        $repo->insert('Evt', ['payload' => 'x'], new \DateTimeImmutable());

        $svc = new DlqService($repo);

        $captured = '';
        $h = \Patchwork\replace('error_log', function ($msg) use (&$captured) { $captured = $msg; return true; });
        $svc->replay(1);
        \Patchwork\restore($h);
        $this->assertStringContainsString('DlqService::doReplay: Row ID 1 - Fallback error', $captured);
    }

    public function testBackwardCompatibilityWithoutLogger(): void
    {
        $repo = new SpyDlq();
        $svc = new DlqService($repo);
        $this->assertInstanceOf(DlqService::class, $svc);
    }
}


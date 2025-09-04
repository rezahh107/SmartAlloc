<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\DlqService;
use SmartAlloc\Tests\TestDoubles\SpyDlq;
use Psr\Log\LoggerInterface;
use SmartAlloc\Infrastructure\Contracts\DlqRepository;

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

    public function testReplayLogsErrorWithStructuredLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'DlqService::doReplay failed for row',
                $this->callback(static fn($context) => isset($context['method'], $context['row_id'], $context['exception']))
            );

        $repo = new class implements DlqRepository {
            public array $rows = [];
            public function insert(string $topic, array $payload, \DateTimeImmutable $createdAtUtc): bool
            {
                $this->rows[] = ['id' => count($this->rows) + 1, 'event_name' => $topic, 'payload' => $payload];
                return true;
            }
            public function listRecent(int $limit): array
            {
                return array_slice($this->rows, 0, $limit);
            }
            public function get(int $id): ?array
            {
                foreach ($this->rows as $row) {
                    if ($row['id'] === $id) {
                        return $row;
                    }
                }
                return null;
            }
            public function delete(int $id): bool
            {
                throw new \Exception('Test error');
            }
            public function count(): int
            {
                return count($this->rows);
            }
        };

        $svc = new DlqService($repo, $logger);
        $svc->push(['event_name' => 'Evt', 'payload' => []]);
        $svc->replay(1);
    }

    public function testReplayUsesErrorLogFallback(): void
    {
        $repo = new class implements DlqRepository {
            public array $rows = [];
            public function insert(string $topic, array $payload, \DateTimeImmutable $createdAtUtc): bool
            {
                $this->rows[] = ['id' => count($this->rows) + 1, 'event_name' => $topic, 'payload' => $payload];
                return true;
            }
            public function listRecent(int $limit): array
            {
                return array_slice($this->rows, 0, $limit);
            }
            public function get(int $id): ?array
            {
                foreach ($this->rows as $row) {
                    if ($row['id'] === $id) {
                        return $row;
                    }
                }
                return null;
            }
            public function delete(int $id): bool
            {
                throw new \Exception('Fallback error');
            }
            public function count(): int
            {
                return count($this->rows);
            }
        };

        $svc = new DlqService($repo);
        $svc->push(['event_name' => 'Evt', 'payload' => []]);

        $tmpLog = tempnam(sys_get_temp_dir(), 'dlq');
        $orig   = ini_get('error_log');
        ini_set('error_log', $tmpLog);
        $svc->replay(1);
        ini_set('error_log', (string) $orig);
        $this->assertStringContainsString('Row ID 1 - Fallback error', (string) file_get_contents($tmpLog));
        @unlink($tmpLog);
    }

}


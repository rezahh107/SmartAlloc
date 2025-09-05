<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\DlqService;
use SmartAlloc\Tests\TestDoubles\SpyDlq;
use Psr\Log\LoggerInterface;
use SmartAlloc\Infrastructure\Contracts\DlqRepository;
use SmartAlloc\Exceptions\RepositoryException;

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
                'dlq.replay_failed',
                $this->callback(static fn($context) => (
                    isset(
                        $context['row_id'],
                        $context['event_name'],
                        $context['attempts'],
                        $context['exception_message'],
                        $context['exception_class']
                    )
                ))
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
        $svc->push(['event_name' => 'Evt', 'payload' => ['attempts' => 2]]);
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
        $this->assertStringContainsString(
            'DLQ Replay Failed - Row ID: 1, Event: Evt, Exception: Fallback error (Exception)',
            (string) file_get_contents($tmpLog)
        );
        @unlink($tmpLog);
    }

    public function testSuccessfulReplayDoesNotLogError(): void
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
                return true;
            }
            public function count(): int
            {
                return count($this->rows);
            }
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $svc = new DlqService($repo, $logger);
        $svc->push(['event_name' => 'Evt', 'payload' => []]);
        $svc->replay(1);
    }

    public function testReplayWarnsWhenIterationExceedsBudget(): void
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
                return $this->rows;
            }
            public function get(int $id): ?array
            {
                return $this->rows[0] ?? null;
            }
            public function delete(int $id): bool
            {
                usleep(200000); // 200ms to trigger budget warning
                return true;
            }
            public function count(): int
            {
                return count($this->rows);
            }
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'DlqService::doReplay iteration',
                $this->callback(static fn($context) => ($context['duration_ms'] ?? 0) > 150)
            );

        $svc = new DlqService($repo, $logger);
        $svc->push(['event_name' => 'Evt', 'payload' => []]);
        $svc->replay(1);
    }

    public function testPushLogsAndRethrowsOnRepositoryError(): void
    {
        $repo = $this->createMock(DlqRepository::class);
        $repo->method('insert')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'dlq.push_failed',
                $this->callback(static fn($ctx) => ($ctx['event_name'] ?? '') === 'Evt')
            );

        $svc = new DlqService($repo, $logger);

        try {
            $svc->push(['event_name' => 'Evt', 'payload' => []]);
            $this->fail('Expected RepositoryException');
        } catch (RepositoryException $e) {
            $this->assertSame('dlq_push', $e->getOperation());
            $this->assertSame('Evt', $e->getContext()['event_name']);
        }
    }

}


<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Domain\Allocation\AllocationStatus;
use SmartAlloc\Infra\Repository\AllocationsRepository;

final class AllocationsRepositoryTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('sanitize_key')->alias(fn($v) => $v);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makeRepo(wpdb $wpdb, LoggerStub $logger): AllocationsRepository
    {
        return new AllocationsRepository($logger, $wpdb);
    }

    public function test_save_rejects_invalid_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $repo = $this->makeRepo(new wpdb(), new LoggerStub());
        $repo->save(1, 'bogus');
    }

    public function test_save_and_find_roundtrip_with_candidates_json(): void
    {
        $wpdb = new wpdb();
        $logger = new LoggerStub();
        $repo = $this->makeRepo($wpdb, $logger);
        $candidates = [['mentor_id' => 1], ['mentor_id' => 2]];
        $repo->save(10, AllocationStatus::MANUAL, null, $candidates);

        $row = $repo->findByEntryId(10);
        $this->assertNotNull($row);
        $this->assertSame(AllocationStatus::MANUAL, $row['status']);
        $this->assertSame($candidates, $row['candidates']);
    }

    public function test_find_handles_corrupted_candidates_json_gracefully(): void
    {
        $wpdb = new wpdb();
        $logger = new LoggerStub();
        $wpdb->rows[5] = [
            'entry_id' => 5,
            'status' => AllocationStatus::MANUAL,
            'mentor_id' => null,
            'candidates' => '{bad json',
        ];
        $repo = $this->makeRepo($wpdb, $logger);

        $row = $repo->findByEntryId(5);
        $this->assertNotNull($row);
        $this->assertSame([], $row['candidates']);
        $this->assertCount(1, $logger->warnings);
    }

    public function test_approveManual_updates_status_and_commits(): void
    {
        $wpdb = new wpdb();
        $logger = new LoggerStub();
        $wpdb->mentors[10] = ['assigned' => 0, 'capacity' => 1];
        $wpdb->rows[1] = ['entry_id' => 1, 'status' => AllocationStatus::MANUAL, 'mentor_id' => null, 'candidates' => json_encode([[ 'mentor_id' => 10 ]])];
        $repo = $this->makeRepo($wpdb, $logger);

        $res = $repo->approveManual(1, 10, 99, null);
        $this->assertTrue($res->to_array()['committed']);
        $this->assertSame(AllocationStatus::AUTO, $wpdb->rows[1]['status']);
        $this->assertSame(10, $wpdb->rows[1]['mentor_id']);
    }

    public function test_rejectManual_updates_status_and_reason(): void
    {
        $wpdb = new wpdb();
        $logger = new LoggerStub();
        $wpdb->rows[2] = ['entry_id' => 2, 'status' => AllocationStatus::MANUAL, 'mentor_id' => null, 'candidates' => null];
        $repo = $this->makeRepo($wpdb, $logger);

        $repo->rejectManual(2, 5, 'duplicate', 'note');
        $this->assertSame(AllocationStatus::REJECT, $wpdb->rows[2]['status']);
        $this->assertSame('duplicate', $wpdb->rows[2]['reason_code']);
    }

    public function test_save_duplicate_throws(): void
    {
        $wpdb = new wpdb();
        $logger = new LoggerStub();
        $repo = $this->makeRepo($wpdb, $logger);
        $repo->save(1, AllocationStatus::MANUAL);
        $this->expectException(RuntimeException::class);
        $repo->save(1, AllocationStatus::MANUAL);
    }
}

class LoggerStub implements LoggerInterface
{
    public array $warnings = [];
    public function debug(string $message, array $context = []): void {}
    public function info(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void { $this->warnings[] = [$message, $context]; }
    public function error(string $message, array $context = []): void {}
}

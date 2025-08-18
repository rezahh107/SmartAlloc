<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Domain\Allocation\AllocationStatus;
use SmartAlloc\Infra\Repository\AllocationsRepository;

final class AllocationsRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makeRepo(WpdbStub $wpdb, LoggerStub $logger): AllocationsRepository
    {
        return new AllocationsRepository($logger, $wpdb);
    }

    public function test_save_rejects_invalid_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $repo = $this->makeRepo(new WpdbStub(), new LoggerStub());
        $repo->save(1, 'bogus');
    }

    public function test_save_and_find_roundtrip_with_candidates_json(): void
    {
        $wpdb = new WpdbStub();
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
        $wpdb = new WpdbStub();
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
}

class LoggerStub implements LoggerInterface
{
    public array $warnings = [];
    public function debug(string $message, array $context = []): void {}
    public function info(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void { $this->warnings[] = [$message, $context]; }
    public function error(string $message, array $context = []): void {}
}

if (!class_exists('wpdb')) {
    class wpdb {}
}

if (!class_exists('WpdbStub')) {
    class WpdbStub extends wpdb
    {
        public string $prefix = 'wp_';
        public array $rows = [];
        public string $last_error = '';

        public function prepare(string $query, ...$args): string
        {
            foreach ($args as &$a) {
                $a = is_numeric($a) ? (int) $a : $a;
            }
            return vsprintf(str_replace('%d', '%u', $query), $args);
        }

        public function get_row(string $sql, $output = ARRAY_A)
        {
            if (preg_match('/entry_id = (\d+)/', $sql, $m)) {
                $id = (int) $m[1];
                return $this->rows[$id] ?? null;
            }
            return null;
        }

        public function insert(string $table, array $data)
        {
            $id = $data['entry_id'];
            if (isset($this->rows[$id])) {
                $this->last_error = 'duplicate';
                return false;
            }
            $this->rows[$id] = $data;
            return 1;
        }
    }
}

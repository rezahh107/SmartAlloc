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
        Functions\when('sanitize_key')->alias(fn($v) => $v);
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

    public function test_approveManual_updates_status_and_commits(): void
    {
        $wpdb = new WpdbStub();
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
        $wpdb = new WpdbStub();
        $logger = new LoggerStub();
        $wpdb->rows[2] = ['entry_id' => 2, 'status' => AllocationStatus::MANUAL, 'mentor_id' => null, 'candidates' => null];
        $repo = $this->makeRepo($wpdb, $logger);

        $repo->rejectManual(2, 5, 'duplicate', 'note');
        $this->assertSame(AllocationStatus::REJECT, $wpdb->rows[2]['status']);
        $this->assertSame('duplicate', $wpdb->rows[2]['reason_code']);
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
      public array $mentors = [];
      public string $last_error = '';
      public int $rows_affected = 0;

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

      public function query(string $sql)
      {
          if (stripos($sql, 'START TRANSACTION') !== false || stripos($sql, 'COMMIT') !== false || stripos($sql, 'ROLLBACK') !== false) {
              return 1;
          }
          if (preg_match('/UPDATE wp_salloc_mentors SET assigned = assigned \+ 1 WHERE mentor_id = (\d+)/', $sql, $m)) {
              $id = (int) $m[1];
              $mentor = $this->mentors[$id] ?? ['assigned' => 0, 'capacity' => 0];
              if ($mentor['assigned'] < $mentor['capacity']) {
                  $mentor['assigned']++;
                  $this->mentors[$id] = $mentor;
                  $this->rows_affected = 1;
              } else {
                  $this->rows_affected = 0;
              }
              return 1;
          }
          if (preg_match("/UPDATE wp_smartalloc_allocations SET status = '([^']+)'/i", $sql, $m)) {
              if (preg_match('/WHERE entry_id = (\d+)/', $sql, $m2)) {
                  $id = (int) $m2[1];
                  if (!isset($this->rows[$id])) {
                      $this->rows_affected = 0;
                      return 0;
                  }
                  $status = $m[1];
                  $this->rows[$id]['status'] = $status;
                  if (preg_match('/mentor_id = (\d+)/', $sql, $m3)) {
                      $this->rows[$id]['mentor_id'] = (int) $m3[1];
                  }
                  if (preg_match("/reason_code = '([^']+)'/", $sql, $m4)) {
                      $this->rows[$id]['reason_code'] = $m4[1];
                  }
                  $this->rows[$id]['reviewer_id'] = 1;
                  $this->rows[$id]['review_notes'] = '';
                  $this->rows[$id]['reviewed_at'] = 'now';
                  $this->rows_affected = 1;
                  return 1;
              }
          }
          return 0;
      }
  }
}

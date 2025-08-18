<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Infra\GF\SabtEntryMapper;
use SmartAlloc\Infra\GF\SabtSubmissionHandler;
use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Services\AllocationService;

final class SubmissionFlowE2E extends \PHPUnit\Framework\TestCase
{
    private array $options;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->options = [
            'allocation_mode' => 'direct',
            'fuzzy_auto_threshold' => 0.90,
            'fuzzy_manual_min' => 0.80,
            'fuzzy_manual_max' => 0.89,
        ];
        $GLOBALS['sa_options'] = ['smartalloc_settings' => $this->options];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function handler(array $allocationResult, WpdbStub $wpdb): SabtSubmissionHandler
    {
        $mapper = new SabtEntryMapper();
        $allocator = new class($allocationResult) extends AllocationService {
            public int $called = 0;
            public function __construct(private array $result) {}
            public function assign(array $student): array { $this->called++; return $this->result; }
        };
        $logger = new class implements LoggerInterface {
            public function debug(string $message, array $context = []): void {}
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };
        $repo = new AllocationsRepository($logger, $wpdb);
        return new SabtSubmissionHandler($mapper, $allocator, $logger, $repo);
    }

    public function test_direct_mode_auto_manual_reject_branches(): void
    {
        $wpdb = new WpdbStub();

        // Auto branch
        $autoRes = ['committed' => true, 'mentor_id' => 5, 'school_match_score' => 0.95];
        $this->handler($autoRes, $wpdb)->process(['id' => 1, '20' => '09123456789', '76' => '1234567890123456'], []);

        // Manual branch
        $manualRes = ['committed' => false, 'school_match_score' => 0.85, 'candidates' => [ ['mentor_id'=>1], ['mentor_id'=>2] ]];
        $this->handler($manualRes, $wpdb)->process(['id' => 2, '20' => '09123456789', '76' => '1234567890123456'], []);

        // Reject branch
        $rejectRes = ['committed' => false, 'school_match_score' => 0.5, 'reason' => 'no_match'];
        $this->handler($rejectRes, $wpdb)->process(['id' => 3, '20' => '09123456789', '76' => '1234567890123456'], []);

        $this->assertSame('auto', $wpdb->rows[1]['status']);
        $this->assertSame(5, $wpdb->rows[1]['mentor_id']);

        $this->assertSame('manual', $wpdb->rows[2]['status']);
        $cand = json_decode($wpdb->rows[2]['candidates'], true);
        $this->assertCount(2, $cand);

        $this->assertSame('reject', $wpdb->rows[3]['status']);
        $rej = json_decode($wpdb->rows[3]['candidates'], true);
        $this->assertSame('no_match', $rej['reason']);
    }

    public function test_rest_mode_equivalence_persists_same_result(): void
    {
        $wpdb = new WpdbStub();
        $res = ['committed' => true, 'mentor_id' => 7, 'school_match_score' => 0.93];
        $this->handler($res, $wpdb)->process(['id' => 10, '20' => '09123456789', '76' => '1234567890123456'], []);

        $this->options['allocation_mode'] = 'rest';
        $GLOBALS['sa_options'] = ['smartalloc_settings' => $this->options];
        Functions\expect('rest_url')->andReturn('http://example.com');
        Functions\expect('wp_remote_post')->andReturn(['body' => json_encode(['result' => $res])]);
        $this->handler([], $wpdb)->process(['id' => 11, '20' => '09123456789', '76' => '1234567890123456'], []);

        $this->assertSame($wpdb->rows[10]['status'], $wpdb->rows[11]['status']);
        $this->assertSame($wpdb->rows[10]['mentor_id'], $wpdb->rows[11]['mentor_id']);
    }

    public function test_idempotency_by_entry_id_returns_prior_result(): void
    {
        $wpdb = new WpdbStub();
        $res = ['committed' => true, 'mentor_id' => 1, 'school_match_score' => 0.95];
        $handler = $this->handler($res, $wpdb);
        $entry = ['id' => 20, '20' => '09123456789', '76' => '1234567890123456'];
        $handler->process($entry, []);
        $handler->process($entry, []);
        $this->assertCount(1, $wpdb->rows);
    }

    public function test_populate_anything_hint_respected_but_not_forced(): void
    {
        $wpdb = new WpdbStub();
        $allocator = new class extends AllocationService {
            public array $student = [];
            public function assign(array $student): array { $this->student = $student; return ['committed'=>true,'mentor_id'=>10,'school_match_score'=>0.99]; }
        };
        $mapper = new SabtEntryMapper();
        $logger = new class implements LoggerInterface {
            public function debug(string $message, array $context = []): void {}
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };
        $repo = new AllocationsRepository($logger, $wpdb);
        $handler = new SabtSubmissionHandler($mapper, $allocator, $logger, $repo);
        $handler->process(['id'=>30,'20'=>'09123456789','76'=>'1234567890123456','39'=>'77'], []);
        $this->assertSame('77', $allocator->student['mentor_select']);
        $this->assertSame(10, $wpdb->rows[30]['mentor_id']);
    }
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
        public int $rows_affected = 0;

        public function prepare(string $query, ...$args): string
        {
            foreach ($args as &$a) { $a = is_numeric($a) ? (int)$a : "'{$a}'"; }
            $query = str_replace('%d', '%u', $query);
            return vsprintf($query, $args);
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

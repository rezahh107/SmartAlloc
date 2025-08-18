<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Infra\GF\SabtEntryMapper;
use SmartAlloc\Infra\GF\SabtSubmissionHandler;
use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Services\AllocationService;

final class SabtSubmissionHandlerTest extends \PHPUnit\Framework\TestCase
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

    private function makeHandler(array $allocationResult, WpdbStub $wpdb): SabtSubmissionHandler
    {
        $mapper = new SabtEntryMapper();
        $allocator = new class($allocationResult) extends AllocationService {
            public int $called = 0; public function __construct(private array $result) {}
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

    public function test_handle_rejects_invalid_mapper_output_records_reason(): void
    {
        $wpdb = new WpdbStub();

        $handler = $this->makeHandler([], $wpdb);
        $handler->process(['id' => 1, '20' => '091234'], []);

        $this->assertArrayHasKey(1, $wpdb->rows);
        $this->assertSame('reject', $wpdb->rows[1]['status']);
    }

    public function test_handle_direct_mode_auto_commit_saves_allocation(): void
    {
        $wpdb = new WpdbStub();

        $allocRes = ['committed' => true, 'mentor_id' => 10, 'school_match_score' => 0.95];
        $handler = $this->makeHandler($allocRes, $wpdb);
        $handler->process(['id' => 2, '20'=>'09123456789', '76'=>'1234567890123456'], []);

        $this->assertSame('auto', $wpdb->rows[2]['status']);
        $this->assertSame(10, $wpdb->rows[2]['mentor_id']);
    }

    public function test_handle_fuzzy_manual_enqueues_candidates_without_commit(): void
    {
        $wpdb = new WpdbStub();

        $allocRes = [
            'committed' => false,
            'school_match_score' => 0.85,
            'candidates' => [ ['mentor_id'=>1], ['mentor_id'=>2], ['mentor_id'=>3] ]
        ];
        $handler = $this->makeHandler($allocRes, $wpdb);
        $handler->process(['id' => 3, '20'=>'09123456789', '76'=>'1234567890123456'], []);

        $this->assertSame('manual', $wpdb->rows[3]['status']);
        $this->assertNull($wpdb->rows[3]['mentor_id']);
        $this->assertNotNull($wpdb->rows[3]['candidates']);
    }

    public function test_handle_fuzzy_reject_records_without_commit(): void
    {
        $wpdb = new WpdbStub();

        $allocRes = [
            'committed' => false,
            'school_match_score' => 0.5,
            'reason' => 'no_match'
        ];
        $handler = $this->makeHandler($allocRes, $wpdb);
        $handler->process(['id' => 4, '20'=>'09123456789', '76'=>'1234567890123456'], []);

        $this->assertSame('reject', $wpdb->rows[4]['status']);
        $this->assertNull($wpdb->rows[4]['mentor_id']);
    }

    public function test_idempotency_returns_existing_result_on_repeat_entry(): void
    {
        $wpdb = new WpdbStub();

        $allocRes = ['committed' => true, 'mentor_id' => 5, 'school_match_score' => 0.95];
        $handler = $this->makeHandler($allocRes, $wpdb);
        $entry = ['id' => 5, '20'=>'09123456789', '76'=>'1234567890123456'];
        $handler->process($entry, []);
        $handler->process($entry, []);

        $this->assertCount(1, $wpdb->rows);
    }

    public function test_handle_rest_mode_calls_allocate_endpoint_and_persists_result(): void
    {
        $wpdb = new WpdbStub();
        Functions\expect('rest_url')->andReturn('http://example.com');
        Functions\expect('wp_remote_post')->andReturn([
            'body' => json_encode(['result' => ['committed' => true, 'mentor_id' => 7, 'school_match_score' => 0.93]])
        ]);

        $this->options['allocation_mode'] = 'rest';
        $GLOBALS['sa_options'] = ['smartalloc_settings' => $this->options];
        $handler = $this->makeHandler([], $wpdb);
        $handler->process(['id' => 6, '20'=>'09123456789', '76'=>'1234567890123456'], []);

        $this->assertSame('auto', $wpdb->rows[6]['status']);
        $this->assertSame(7, $wpdb->rows[6]['mentor_id']);
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
        public array $results = [];
        public int $var = 0;
        public array $mentors = [];
        public string $last_error = '';
        public int $rows_affected = 0;

        public function prepare(string $query, ...$args): string
        {
            foreach ($args as &$a) {
                $a = is_numeric($a) ? (int)$a : "'{$a}'";
            }
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

        public function get_results($sql, $output = ARRAY_A)
        {
            return $this->results;
        }

        public function get_var($sql)
        {
            return $this->var;
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
                    $this->rows_affected = 1;
                    return 1;
                }
            }
            return 0;
        }
    }
}

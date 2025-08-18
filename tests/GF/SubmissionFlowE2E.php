<?php
declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use SmartAlloc\Bootstrap;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Infra\GF\SabtSubmissionHandler;
use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\Logging;

final class SubmissionFlowE2E extends TestCase
{
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        if (function_exists('sa_cache_flush')) {
            sa_cache_flush();
        }
        $this->wpdb = new WpdbStub();
        $GLOBALS['wpdb'] = $this->wpdb;

        sa_bootstrap_reset();
        Bootstrap::init();

        $logger = new class implements LoggerInterface {
            public function debug(string $message, array $context = []): void {}
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };
        Bootstrap::container()->set(LoggerInterface::class, fn() => $logger);
        Bootstrap::container()->set(Logging::class, fn() => $logger);
        Bootstrap::container()->set(AllocationsRepository::class, fn() => new AllocationsRepository($logger, $this->wpdb));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_direct_mode_auto_manual_reject_branches(): void
    {
        $GLOBALS['sa_options'] = ['smartalloc_settings' => [
            'allocation_mode' => 'direct',
            'fuzzy_auto_threshold' => 0.90,
            'fuzzy_manual_min' => 0.80,
            'fuzzy_manual_max' => 0.89,
        ]];

        $results = [
            1 => ['committed' => true, 'mentor_id' => 10, 'school_match_score' => 0.95],
            2 => ['committed' => false, 'school_match_score' => 0.85, 'candidates' => [ ['mentor_id'=>1], ['mentor_id'=>2] ]],
            3 => ['committed' => false, 'school_match_score' => 0.5, 'reason' => 'no_match'],
        ];
        $allocator = new class($results) extends AllocationService {
            public function __construct(private array $map) {}
            public function assign(array $student): array { return $this->map[$student['id']] ?? []; }
        };
        Bootstrap::container()->set(AllocationService::class, fn() => $allocator);

        foreach ([1,2,3] as $id) {
            SabtSubmissionHandler::handle(['id'=>$id,'20'=>'09123456789','76'=>'1234567890123456'], []);
        }

        $this->assertSame('auto', $this->wpdb->rows[1]['status']);
        $this->assertSame(10, $this->wpdb->rows[1]['mentor_id']);

        $this->assertSame('manual', $this->wpdb->rows[2]['status']);
        $this->assertNull($this->wpdb->rows[2]['mentor_id']);
        $this->assertNotNull($this->wpdb->rows[2]['candidates']);

        $this->assertSame('reject', $this->wpdb->rows[3]['status']);
        $this->assertSame('no_match', $this->wpdb->rows[3]['reason_code']);
    }

    public function test_rest_mode_equivalence_persists_same_result(): void
    {
        $GLOBALS['sa_options'] = ['smartalloc_settings' => [
            'allocation_mode' => 'rest',
            'fuzzy_auto_threshold' => 0.90,
            'fuzzy_manual_min' => 0.80,
            'fuzzy_manual_max' => 0.89,
        ]];

        Functions\expect('rest_url')->andReturn('http://example.com');
        Functions\expect('wp_remote_post')->andReturn([
            'body' => json_encode(['result' => ['committed' => true, 'mentor_id' => 7, 'school_match_score' => 0.95]])
        ]);
        Functions\expect('is_wp_error')->andReturn(false);

        SabtSubmissionHandler::handle(['id'=>20,'20'=>'09123456789','76'=>'1234567890123456'], []);

        $this->assertSame('auto', $this->wpdb->rows[20]['status']);
        $this->assertSame(7, $this->wpdb->rows[20]['mentor_id']);
    }

    public function test_idempotency_by_entry_id_returns_prior_result(): void
    {
        $GLOBALS['sa_options'] = ['smartalloc_settings' => [
            'allocation_mode' => 'direct',
            'fuzzy_auto_threshold' => 0.90,
            'fuzzy_manual_min' => 0.80,
            'fuzzy_manual_max' => 0.89,
        ]];

        $allocator = new class extends AllocationService {
            public int $calls = 0;
            public function __construct() {}
            public function assign(array $student): array { $this->calls++; return ['committed'=>true,'mentor_id'=>5,'school_match_score'=>0.95]; }
        };
        Bootstrap::container()->set(AllocationService::class, fn() => $allocator);

        $entry = ['id'=>30,'20'=>'09123456789','76'=>'1234567890123456'];
        SabtSubmissionHandler::handle($entry, []);
        SabtSubmissionHandler::handle($entry, []);

        $this->assertCount(1, $this->wpdb->rows);
        $this->assertSame(1, $allocator->calls);
        $this->assertSame(5, $this->wpdb->rows[30]['mentor_id']);
    }

    public function test_populate_anything_hint_respected_not_forced(): void
    {
        $GLOBALS['sa_options'] = ['smartalloc_settings' => [
            'allocation_mode' => 'direct',
            'fuzzy_auto_threshold' => 0.90,
            'fuzzy_manual_min' => 0.80,
            'fuzzy_manual_max' => 0.89,
        ]];

        $allocator = new class extends AllocationService {
            public array $lastStudent = [];
            public function __construct() {}
            public function assign(array $student): array {
                $this->lastStudent = $student;
                if ($GLOBALS['override_hint'] ?? false) {
                    return ['committed'=>true,'mentor_id'=>(int)$student['mentor_select'],'school_match_score'=>0.99];
                }
                return ['committed'=>true,'mentor_id'=>1,'school_match_score'=>0.95];
            }
        };
        Bootstrap::container()->set(AllocationService::class, fn() => $allocator);

        $GLOBALS['override_hint'] = false;
        SabtSubmissionHandler::handle(['id'=>40,'20'=>'09123456789','76'=>'1234567890123456','39'=>'77'], []);
        $this->assertSame('77', $allocator->lastStudent['mentor_select']);
        $this->assertSame(1, $this->wpdb->rows[40]['mentor_id']);

        $GLOBALS['override_hint'] = true;
        SabtSubmissionHandler::handle(['id'=>41,'20'=>'09123456789','76'=>'1234567890123456','39'=>'88'], []);
        $this->assertSame(88, $this->wpdb->rows[41]['mentor_id']);
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
            if (preg_match('/entry_id = (\\d+)/', $sql, $m)) {
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
            if (preg_match("/UPDATE wp_salloc_mentors SET assigned = assigned \\+ 1 WHERE mentor_id = (\\d+)/", $sql, $m)) {
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
                if (preg_match('/WHERE entry_id = (\\d+)/', $sql, $m2)) {
                    $id = (int)$m2[1];
                    if (!isset($this->rows[$id])) {
                        $this->rows_affected = 0;
                        return 0;
                    }
                    $status = $m[1];
                    $this->rows[$id]['status'] = $status;
                    if (preg_match('/mentor_id = (\\d+)/', $sql, $m3)) {
                        $this->rows[$id]['mentor_id'] = (int)$m3[1];
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

<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Domain\Allocation\AllocationResult;
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

    private function makeHandler(array $allocationResult, wpdb $wpdb): SabtSubmissionHandler
    {
        $mapper = new SabtEntryMapper();
        $allocator = new class($allocationResult) extends AllocationService {
            public int $called = 0; public function __construct(private array $result) {}
            public function assign(array $student): AllocationResult { $this->called++; return new AllocationResult($this->result); }
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
        $wpdb = new wpdb();

        $handler = $this->makeHandler([], $wpdb);
        $handler->process(['id' => 1, '20' => '091234'], []);

        $this->assertArrayHasKey(1, $wpdb->rows);
        $this->assertSame('reject', $wpdb->rows[1]['status']);
    }

    public function test_handle_direct_mode_auto_commit_saves_allocation(): void
    {
        $wpdb = new wpdb();

        $allocRes = ['committed' => true, 'mentor_id' => 10, 'school_match_score' => 0.95];
        $handler = $this->makeHandler($allocRes, $wpdb);
        $handler->process(['id' => 2, '20'=>'09123456789', '76'=>'1234567890123456'], []);

        $this->assertSame('auto', $wpdb->rows[2]['status']);
        $this->assertSame(10, $wpdb->rows[2]['mentor_id']);
    }

    public function test_handle_fuzzy_manual_enqueues_candidates_without_commit(): void
    {
        $wpdb = new wpdb();

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
        $wpdb = new wpdb();

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
        $wpdb = new wpdb();

        $allocRes = ['committed' => true, 'mentor_id' => 5, 'school_match_score' => 0.95];
        $handler = $this->makeHandler($allocRes, $wpdb);
        $entry = ['id' => 5, '20'=>'09123456789', '76'=>'1234567890123456'];
        $handler->process($entry, []);
        $handler->process($entry, []);

        $this->assertCount(1, $wpdb->rows);
    }

    public function test_handle_rest_mode_calls_allocate_endpoint_and_persists_result(): void
    {
        $wpdb = new wpdb();
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


<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\ScoringAllocator;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\EventStoreInterface;
use SmartAlloc\Domain\Allocation\AllocationResult;

if (!class_exists('WP_Error')) { class WP_Error { public function __construct(public string $code = '', public string $message = '', public array $data = []) {} public function get_error_data(): array { return $this->data; } } }

final class AllocationHappyPathTest extends BaseTestCase
{
    public function testStudentAllocatedAndEventsEmitted(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public int $rows_affected = 0;
            public array $mentors = [1 => [
                'mentor_id' => 1,
                'gender' => 'M',
                'center' => 'C',
                'capacity' => 1,
                'assigned' => 0,
                'active' => 1,
            ]];
            public array $history = [];
            public function get_results($sql,$mode){ return array_values($this->mentors); }
            public function query($sql){ if($this->mentors[1]['assigned']<1){$this->mentors[1]['assigned']++;$this->rows_affected=1;}else{$this->rows_affected=0;}}
            public function insert($table,$data){ if(str_contains($table,'history')){ $this->history[]=$data; } }
            public function prepare($sql,$id){ return $sql; }
        };
        $GLOBALS['wpdb'] = $wpdb;

        $logger = new Logging();
        $eventStore = new class implements EventStoreInterface {
            public function insertEventIfNotExists(string $event, string $dedupeKey, array $payload): int { return 1; }
            public function startListenerRun(int $eventLogId, string $listener): int { return 1; }
            public function finishListenerRun(int $listenerRunId, string $status, ?string $error, int $durationMs): void {}
            public function finishEvent(int $eventLogId, string $status, ?string $error, int $durationMs): void {}
        };
        $eventBus = new EventBus($logger, $eventStore);
        $metrics = new Metrics();

        $service = new AllocationService($logger, $eventBus, $metrics, new ScoringAllocator());
        $result = $service->assign(['id'=>10,'gender'=>'M','center'=>'C']);
        $this->assertInstanceOf(AllocationResult::class,$result);
        $this->assertCount(1,$wpdb->history);
    }
}

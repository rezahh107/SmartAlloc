<?php
declare(strict_types=1);
if (!class_exists('WP_Error')) { class WP_Error { public function __construct(public string $code = '', public string $message = '', public array $data = []) {} public function get_error_data(): array { return $this->data; } } }


use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\ScoringAllocator;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\EventStoreInterface;
use SmartAlloc\Domain\Allocation\AllocationResult;

final class GuardedUpdateTest extends BaseTestCase
{
    private function service(&$wpdb): AllocationService
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
            public function get_results($sql, $mode) { return array_values($this->mentors); }
            public function query($sql) { if ($this->mentors[1]['assigned'] < $this->mentors[1]['capacity']) { $this->mentors[1]['assigned']++; $this->rows_affected=1; } else { $this->rows_affected=0; } }
            public function insert($t,$d){}
            public function prepare($sql,$id){ return $sql; }
        };
        $logger = new Logging();
        $eventStore = new class implements EventStoreInterface {
            public function insertEventIfNotExists(string $event, string $dedupeKey, array $payload): int { return 1; }
            public function startListenerRun(int $eventLogId, string $listener): int { return 1; }
            public function finishListenerRun(int $listenerRunId, string $status, ?string $error, int $durationMs): void {}
            public function finishEvent(int $eventLogId, string $status, ?string $error, int $durationMs): void {}
        };
        $eventBus = new EventBus($logger, $eventStore);
        $metrics = new Metrics();
        return new AllocationService($logger, $eventBus, $metrics, new ScoringAllocator());
    }

    public function testUpdateFailsWhenCapacityExhausted(): void
    {
        $service = $this->service($GLOBALS['wpdb']);
        $result1 = $service->assign(['id' => 1, 'gender' => 'M', 'center' => 'C']);
        $result2 = $service->assign(['id' => 2, 'gender' => 'M', 'center' => 'C']);
        $this->assertInstanceOf(AllocationResult::class, $result1);
        $this->assertInstanceOf(WP_Error::class, $result2);
    }
}

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

class AllocationServiceConcurrencyTest extends BaseTestCase
{
    private function makeService(&$wpdb): AllocationService
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public int $rows_affected = 0;
            public array $mentors = [1 => [
                'mentor_id' => 1,
                'gender' => 'M',
                'center' => 'C',
                'capacity' => 5,
                'assigned' => 0,
                'active' => 1,
            ]];
            public function get_results($sql, $mode) { return array_values($this->mentors); }
            public function query($sql) { if ($this->mentors[1]['assigned'] < $this->mentors[1]['capacity']) { $this->mentors[1]['assigned']++; $this->rows_affected=1; } else { $this->rows_affected=0; } }
            public function insert($table, $data) {}
            public function prepare($sql, ...$args){
                $params = is_array($args[0] ?? null) ? $args[0] : $args;
                foreach ($params as $p) {
                    $sql = preg_replace('/%d/', (string)(int)$p, $sql, 1);
                    $sql = preg_replace('/%s/', "'".$p."'", $sql, 1);
                    $sql = preg_replace('/%f/', (string)(float)$p, $sql, 1);
                }
                return $sql;
            }
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

    public function testAssignedDoesNotExceedCapacity(): void
    {
        $service = $this->makeService($GLOBALS['wpdb']);
        $success = 0;
        for ($i = 0; $i < 20; $i++) {
            $result = $service->assign(['id' => $i + 1, 'gender' => 'M', 'center' => 'C']);
            if ($result instanceof AllocationResult) {
                $success++;
            }
        }
        $this->assertSame(5, $success);
        $this->assertSame(5, $GLOBALS['wpdb']->mentors[1]['assigned']);
    }
}

<?php
declare(strict_types=1);

use Eris\TestTrait;
use Eris\Generator;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Services\ScoringAllocator;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\EventStoreInterface;
use PHPUnit\Framework\TestCase;

if (!class_exists('WP_UnitTestCase')) {
    abstract class WP_UnitTestCase extends TestCase {}
}
if (!class_exists('wpdb')) {
    class wpdb {}
}
class WpdbStub extends wpdb
{
    public string $prefix = 'wp_';
    public int $rows_affected = 0;
    public array $history = [];
    public array $mentors;

    public function __construct()
    {
        $this->mentors = [
            1 => ['mentor_id' => 1, 'gender' => 'M', 'center' => '1', 'group_code' => 'EX', 'capacity' => 3, 'assigned' => 0, 'active' => 1],
            2 => ['mentor_id' => 2, 'gender' => 'F', 'center' => '1', 'group_code' => 'MA', 'capacity' => 3, 'assigned' => 0, 'active' => 1],
        ];
    }

    public function get_results($sql, $mode)
    {
        return array_values($this->mentors);
    }

    public function query($sql): void
    {
        if (preg_match('/mentor_id = (\d+)/', $sql, $m)) {
            $id = (int) $m[1];
            if ($this->mentors[$id]['assigned'] < $this->mentors[$id]['capacity']) {
                $this->mentors[$id]['assigned']++;
                $this->rows_affected = 1;
            } else {
                $this->rows_affected = 0;
            }
        } else {
            $this->rows_affected = 0;
        }
    }

    public function insert($table, $data): void
    {
        if (str_contains($table, 'history')) {
            $this->history[] = $data;
        }
    }

    public function prepare($query, ...$args)
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        $query = str_replace(['%d','%s','%f'], ['%u','%s','%F'], $query);
        return vsprintf($query, $args);
    }
}

final class AllocationPropertiesTest extends WP_UnitTestCase
{
    use TestTrait;

    private WpdbStub $db;

    private function makeAllocationEngine(): AllocationService
    {
        $logger = new Logging();
        $eventStore = new class implements EventStoreInterface {
            public function insertEventIfNotExists(string $event, string $dedupeKey, array $payload): int { return 1; }
            public function startListenerRun(int $eventLogId, string $listener): int { return 1; }
            public function finishListenerRun(int $listenerRunId, string $status, ?string $error, int $durationMs): void {}
            public function finishEvent(int $eventLogId, string $status, ?string $error, int $durationMs): void {}
        };
        $eventBus = new EventBus($logger, $eventStore);
        $metrics = new Metrics();
        $this->db = new WpdbStub();
        $GLOBALS['wpdb'] = $this->db;
        return new AllocationService($logger, $eventBus, $metrics, new ScoringAllocator(), $this->db);
    }

    private function makeStudentFromSeed(int $seed): array
    {
        return [
            'id' => abs($seed) + 1,
            'gender' => $seed % 2 === 0 ? 'M' : 'F',
            'center' => '1',
        ];
    }

    private function processAllocations(array $students): void
    {
        $engine = $this->makeAllocationEngine();
        foreach ($students as $student) {
            $engine->assign($student);
        }
    }

    private function getAllMentors(): array
    {
        return $this->db->mentors;
    }

    private function getAssignedCount(int $mentorId): int
    {
        return $this->db->mentors[$mentorId]['assigned'] ?? 0;
    }

    public function test_capacity_never_exceeded_property(): void
    {
        $this
            ->forAll(
                Generator\seq(
                    Generator\associative([
                        'id' => Generator\int(),
                        'gender' => Generator\elements(['M', 'F']),
                        'center' => Generator\elements(['1']),
                    ]),
                    1,
                    20
                )
            )
            ->then(function ($students): void {
                $this->processAllocations($students);
                foreach ($this->getAllMentors() as $mentor) {
                    $this->assertLessThanOrEqual(
                        $mentor['capacity'],
                        $this->getAssignedCount($mentor['mentor_id'])
                    );
                }
            });
    }

    public function test_allocation_deterministic_property(): void
    {
        $this
            ->forAll(Generator\int())
            ->then(function ($seed): void {
                $student = $this->makeStudentFromSeed($seed);
                $engine1 = $this->makeAllocationEngine();
                $engine2 = $this->makeAllocationEngine();
                $r1 = $engine1->assign($student);
                $r2 = $engine2->assign($student);
                $this->assertEquals($r1->get('mentor_id'), $r2->get('mentor_id'));
            });
    }
}

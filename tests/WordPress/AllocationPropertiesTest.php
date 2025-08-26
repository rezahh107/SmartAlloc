<?php
declare(strict_types=1);

use Eris\TestTrait;
use Eris\Generator;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Tests\BaseTestCase;

if (!class_exists('WP_UnitTestCase')) {
    abstract class WP_UnitTestCase extends BaseTestCase {}
}

/**
 * @group wp
 */
final class AllocationPropertiesTest extends WP_UnitTestCase
{
    use TestTrait;

    private wpdb $db;

    private function makeAllocationEngine(): AllocationService
    {
        $this->db = new wpdb();
        $GLOBALS['wpdb'] = $this->db;
        $tables = new TableResolver($this->db);
        return new AllocationService($tables);
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
        if (!method_exists(AllocationService::class, 'assign')) {
            $this->markTestSkipped('Assignment API not available');
        }

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
        if (!method_exists(AllocationService::class, 'assign')) {
            $this->markTestSkipped('Assignment API not available');
        }

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

<?php
namespace SmartAlloc\Tests\DataQuality;

use SmartAlloc\Tests\BaseTestCase;
use function SmartAlloc\Testing\Support\gini;

final class DataQualityTest extends BaseTestCase
{
    private array $students;
    private array $mentors;
    private array $history;

    protected function setUp(): void
    {
        $this->students = [
            ['id' => 1], ['id' => 2], ['id' => 3],
        ];
        $this->mentors = [
            ['mentor_id' => 1, 'assigned' => 1],
            ['mentor_id' => 2, 'assigned' => 2],
        ];
        $this->history = [
            ['student_id' => 1, 'new_mentor_id' => 1],
            ['student_id' => 2, 'new_mentor_id' => 2],
            ['student_id' => 3, 'new_mentor_id' => 2],
        ];
    }

    public function test_referential_integrity_no_orphans(): void
    {
        $studentIds = array_column($this->students, 'id');
        $mentorIds = array_column($this->mentors, 'mentor_id');
        foreach ($this->history as $row) {
            $this->assertContains($row['student_id'], $studentIds);
            $this->assertContains($row['new_mentor_id'], $mentorIds);
        }
    }

    public function test_assigned_counts_match_reality(): void
    {
        $counts = [];
        foreach ($this->history as $row) {
            $counts[$row['new_mentor_id']] = ($counts[$row['new_mentor_id']] ?? 0) + 1;
        }
        foreach ($this->mentors as $m) {
            $this->assertSame($counts[$m['mentor_id']] ?? 0, $m['assigned']);
        }
    }

    public function test_allocation_history_accuracy(): void
    {
        $current = [];
        foreach ($this->history as $row) {
            $current[$row['student_id']] = $row['new_mentor_id'];
        }
        $this->assertSame(2, $current[3]);
    }

    public function test_fair_distribution_gini_under_threshold(): void
    {
        $loads = array_column($this->mentors, 'assigned');
        $g = gini($loads);
        $this->assertLessThanOrEqual(0.35, $g);
    }

    public function test_anomaly_detection_simple_rules(): void
    {
        $loads = array_column($this->mentors, 'assigned');
        $mean = array_sum($loads) / count($loads);
        $variance = array_sum(array_map(fn($l) => ($l - $mean) ** 2, $loads)) / count($loads);
        $sd = sqrt($variance);
        foreach ($loads as $l) {
            $this->assertLessThanOrEqual($mean + 3 * $sd, $l);
        }
    }
}

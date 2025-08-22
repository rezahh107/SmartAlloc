<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use WP_Error;
use SmartAlloc\Domain\Allocation\AllocationResult;
use SmartAlloc\Event\EventBus;

/**
 * Core allocation engine implementing guarded commits and scoring.
 */
final class AllocationService
{
    public function __construct(
        private Logging $logger,
        private EventBus $eventBus,
        private Metrics $metrics,
        private ScoringAllocator $scorer,
    ) {
    }

    /**
     * Assign a student to a mentor.
     *
     * @param array<string,mixed> $student
     * @return AllocationResult|WP_Error
     */
    public function assign(array $student)
    {
        $candidates = $this->loadCandidates($student);
        if (empty($candidates)) {
            return new WP_Error('no_candidate', 'No mentors available', ['status' => 404]);
        }

        $ranked = $this->scorer->rank($candidates);
        foreach ($ranked as $idx => $mentor) {
            if ($this->reserve($mentor['mentor_id'])) {
                $this->persistHistory($mentor['mentor_id'], (int) $student['id']);

                $payload = [
                    'student_id' => (int) $student['id'],
                    'mentor_id'  => (int) $mentor['mentor_id'],
                    'ts_utc'     => gmdate('Y-m-d H:i:s'),
                    'dedupe_key' => 'alloc:' . (int) $student['id'] . ':v1',
                ];
                if (!empty($_SERVER['HTTP_X_TRACE_ID'])) {
                    $payload['trace_id'] = sanitize_text_field((string) $_SERVER['HTTP_X_TRACE_ID']);
                }
                $this->eventBus->dispatch('MentorAssigned', $payload, 'v1');
                $this->eventBus->dispatch('AllocationCommitted', $payload, 'v1');

                $this->metrics->inc('allocations_committed_total');
                return new AllocationResult([
                    'mentor_id' => (int) $mentor['mentor_id'],
                    'score'     => (float) $mentor['score'],
                    'attempt'   => $idx + 1,
                ]);
            }
        }

        return new WP_Error('no_candidate', 'No mentors available', ['status' => 404]);
    }

    /**
     * @param array<string,mixed> $student
     * @return array<int,array<string,mixed>>
     */
    private function loadCandidates(array $student): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_mentors';
        $sql   = "SELECT * FROM {$table} WHERE active = 1 AND assigned < capacity";
        // @security-ok-sql
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

        $filtered = array_values(array_filter($rows, function (array $m) use ($student): bool {
            if (($m['gender'] ?? '') !== ($student['gender'] ?? '')) {
                return false;
            }
            if (!empty($student['group']) && ($m['group_code'] ?? '') !== $student['group']) {
                return false;
            }
            if (!empty($student['grade']) && ($m['grade'] ?? '') !== $student['grade']) {
                return false;
            }
            if (!empty($student['school_id']) && !empty($m['school_id']) && $m['school_id'] != $student['school_id']) {
                return false;
            }
            if (!empty($student['center_id']) && ($m['center_id'] ?? null) != $student['center_id']) {
                return false;
            }
            if (!empty($student['target_manager_id']) && ($m['manager_id'] ?? null) != $student['target_manager_id']) {
                return false;
            }
            return true;
        }));

        return $filtered;
    }

    private function reserve(int $mentorId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_mentors';
        $attempts = 0;
        do {
            $attempts++;
            $sql = $wpdb->prepare(
                "UPDATE {$table} SET assigned = assigned + 1 WHERE mentor_id = %d AND active = 1 AND capacity > assigned",
                $mentorId
            );
            // @security-ok-sql
            $wpdb->query($sql);
            if ($wpdb->rows_affected === 1) {
                return true;
            }
            usleep(random_int(5, 20) * 1000);
        } while ($attempts < 3);
        return false;
    }

    private function persistHistory(int $mentorId, int $studentId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_allocation_history';
        $wpdb->insert($table, [
            'mentor_id'      => $mentorId,
            'student_id'     => $studentId,
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}

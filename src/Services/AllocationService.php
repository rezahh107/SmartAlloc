<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use WP_Error;
use SmartAlloc\Domain\Allocation\AllocationResult;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\ScoringAllocatorInterface;
use SmartAlloc\Services\DbSafe;

/**
 * Core allocation engine implementing guarded commits and scoring.
 */
class AllocationService
{
    public function __construct(
        private Logging $logger,
        private EventBus $eventBus,
        private Metrics $metrics,
        private ScoringAllocatorInterface $scorer,
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

        $ranked = $this->scorer->rank($candidates, $student);
        foreach ($ranked as $idx => $mentor) {
            $commit = $this->commit((int) $mentor['mentor_id'], (int) $student['id']);
            if ($commit['committed']) {
                return new AllocationResult([
                    'mentor_id' => $commit['mentor_id'],
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

        if (empty($student['gender']) || empty($student['center'])) {
            return [];
        }

        $where  = 'active = 1 AND assigned < capacity AND gender = %s AND center = %s';
        $params = [$student['gender'], $student['center']];
        if (!empty($student['group_code'])) {
            $where .= ' AND group_code = %s';
            $params[] = $student['group_code'];
        }
        $sql = "SELECT mentor_id, gender, center, group_code, capacity, assigned FROM {$table} WHERE {$where} ORDER BY mentor_id ASC LIMIT 50";
        $query = DbSafe::mustPrepare($sql, $params);
        // @security-ok-sql
        $rows = $wpdb->get_results($query, ARRAY_A);
        return $rows ?: [];
    }

    /**
     * Attempt to commit allocation to a mentor.
     *
     * @return array{mentor_id:int,committed:bool}
     */
    private function commit(int $mentorId, int $studentId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_mentors';
        $sql = DbSafe::mustPrepare(
            "UPDATE {$table} SET assigned = assigned + 1 WHERE mentor_id = %d AND assigned < capacity",
            [$mentorId]
        );
        // @security-ok-sql
        $wpdb->query($sql);
        if ($wpdb->rows_affected !== 1) {
            return ['mentor_id' => 0, 'committed' => false];
        }

        $history = $wpdb->prefix . 'salloc_alloc_history';
        $wpdb->insert($history, [
            'student_id'     => $studentId,
            'prev_mentor_id' => null,
            'new_mentor_id'  => $mentorId,
            'performed_by'   => 'system',
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);

        $payload = [
            'student_id' => $studentId,
            'mentor_id'  => $mentorId,
            'entry_id'   => $studentId,
            'ts_utc'     => gmdate('Y-m-d H:i:s'),
        ];
        if (!empty($_SERVER['HTTP_X_TRACE_ID'])) {
            $payload['trace_id'] = sanitize_text_field((string) $_SERVER['HTTP_X_TRACE_ID']);
        }
        $this->eventBus->dispatch('MentorAssigned', $payload, 'v1');
        $this->eventBus->dispatch('AllocationCommitted', $payload, 'v1');

        $this->metrics->inc('allocations_committed_total');

        return ['mentor_id' => $mentorId, 'committed' => true];
    }
}

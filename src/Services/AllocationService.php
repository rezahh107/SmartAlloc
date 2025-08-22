<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Domain\Allocation\AllocationResult;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\ScoringAllocatorInterface;
use SmartAlloc\Services\DbSafe;
use InvalidArgumentException;

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
        private \wpdb $wpdb,
    ) {
    }

    /**
     * Assign a student to a mentor.
     *
     * @param array<string,mixed> $student
     */
    public function assign(array $student): AllocationResult
    {
        $student = $this->validateInput($student);

        $candidates = $this->loadCandidates($student);
        if (empty($candidates)) {
            return new AllocationResult([
                'mentor_id' => 0,
                'committed' => false,
                'metadata' => [],
            ]);
        }

        $ranked = $this->scorer->rank($candidates, $student);
        foreach ($ranked as $idx => $mentor) {
            $commit = $this->commit((int) $mentor['mentor_id'], (int) $student['id']);
            if ($commit['committed']) {
                return new AllocationResult([
                    'mentor_id' => $commit['mentor_id'],
                    'score'     => (float) $mentor['score'],
                    'attempt'   => $idx + 1,
                    'committed' => true,
                    'metadata'  => [
                        'score' => (float) $mentor['score'],
                        'selected_strategy' => 'scoring',
                        'tie_breaker' => $idx === 0 ? 'none' : 'rank',
                    ],
                ]);
            }
        }

        return new AllocationResult([
            'mentor_id' => 0,
            'committed' => false,
            'metadata' => [],
        ]);
    }

    /**
     * Validate and normalize student payload.
     *
     * @param array<string,mixed> $payload
     * @return array{student_id:int,center_id:int,gender:string,group_code:?string,preferences:?array,id:int,center:int}
     */
    private function validateInput(array $payload): array
    {
        $allowed = ['student_id','center_id','gender','group_code','preferences','id','center'];
        foreach ($payload as $key => $_) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unexpected field: ' . $key);
            }
        }

        $studentId = $payload['student_id'] ?? $payload['id'] ?? null;
        $centerId  = $payload['center_id'] ?? $payload['center'] ?? null;
        if ($studentId === null || $centerId === null || !isset($payload['gender'])) {
            throw new InvalidArgumentException('Missing required fields');
        }

        $gender = strtoupper((string) $payload['gender']);
        if (!in_array($gender, ['M','F'], true)) {
            throw new InvalidArgumentException('Invalid gender');
        }

        $group = null;
        if (array_key_exists('group_code', $payload)) {
            if ($payload['group_code'] !== null && !is_string($payload['group_code'])) {
                throw new InvalidArgumentException('Invalid group_code');
            }
            $group = $payload['group_code'] !== null ? (string) $payload['group_code'] : null;
        }

        $prefs = null;
        if (array_key_exists('preferences', $payload)) {
            if ($payload['preferences'] !== null && !is_array($payload['preferences'])) {
                throw new InvalidArgumentException('Invalid preferences');
            }
            $prefs = $payload['preferences'];
        }

        return [
            'student_id' => (int) $studentId,
            'center_id'  => (int) $centerId,
            'gender'     => $gender,
            'group_code' => $group,
            'preferences'=> $prefs,
            'id'         => (int) $studentId,
            'center'     => (int) $centerId,
        ];
    }

    /**
     * @param array<string,mixed> $student
     * @return array<int,array<string,mixed>>
     */
    private function loadCandidates(array $student): array
    {
        $table = $this->wpdb->prefix . 'salloc_mentors';

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
        $rows = $this->wpdb->get_results($query, ARRAY_A);
        return $rows ?: [];
    }

    /**
     * Attempt to commit allocation to a mentor.
     *
     * @return array{mentor_id:int,committed:bool}
     */
    private function commit(int $mentorId, int $studentId): array
    {
        $table = $this->wpdb->prefix . 'salloc_mentors';
        $sql = DbSafe::mustPrepare(
            "UPDATE {$table} SET assigned = assigned + 1 WHERE mentor_id = %d AND assigned < capacity",
            [$mentorId]
        );
        // @security-ok-sql
        $this->wpdb->query($sql);
        if ($this->wpdb->rows_affected !== 1) {
            return ['mentor_id' => 0, 'committed' => false];
        }

        $history = $this->wpdb->prefix . 'salloc_alloc_history';
        $this->wpdb->insert($history, [
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

<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Event\EventBus;

/**
 * Enhanced Allocation Service with mentor ranking and fuzzy matching
 */
class AllocationService
{
    private const DEFAULT_CAPACITY = 60;
    private const FUZZY_ACCEPT_THRESHOLD = 0.90;
    private const FUZZY_REVIEW_THRESHOLD = 0.80;

    public function __construct(
        private Db $db,
        private CrosswalkService $crosswalk,
        private Logging $logger,
        private Metrics $metrics,
        private EventBus $eventBus
    ) {}

    /**
     * Assign a student to the best available mentor
     */
    public function assign(array $student): array
    {
        $this->logger->info('allocation.start', ['student_id' => $student['id'] ?? 'unknown']);

        try {
            // Validate student data
            $this->validateStudent($student);

            // Find eligible mentors
            $candidates = $this->findEligibleMentors($student);
            
            if (empty($candidates)) {
                $this->logger->warning('allocation.no_candidates', ['student_id' => $student['id'] ?? 'unknown']);
                return ['committed' => false, 'reason' => 'no_eligible_mentors'];
            }

            // Rank candidates
            $rankedCandidates = $this->rankCandidates($candidates, $student);

            // Try to allocate to the best candidate
            foreach ($rankedCandidates as $candidate) {
                $result = $this->tryAllocation($candidate, $student);
                if ($result['success']) {
                    $this->logger->info('allocation.success', [
                        'student_id' => $student['id'] ?? 'unknown',
                        'mentor_id' => $candidate['mentor_id']
                    ]);
                    
                    // Update metrics
                    $this->metrics->inc('allocations_committed_total');
                    
                    return [
                        'committed' => true,
                        'mentor_id' => $candidate['mentor_id'],
                        'mentor_name' => $candidate['name'],
                        'school_match_score' => $candidate['school_match_score'] ?? 0
                    ];
                }
            }

            $this->logger->warning('allocation.no_capacity', ['student_id' => $student['id'] ?? 'unknown']);
            return ['committed' => false, 'reason' => 'no_capacity'];

        } catch (\Throwable $e) {
            $this->logger->error('allocation.error', [
                'student_id' => $student['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate student data
     */
    private function validateStudent(array $student): void
    {
        $required = ['gender', 'center', 'school_code'];
        foreach ($required as $field) {
            if (empty($student[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Validate gender
        if (!in_array($student['gender'], ['F', 'M'])) {
            throw new \InvalidArgumentException("Invalid gender: {$student['gender']}");
        }

        // Validate center (must be numeric)
        if (!is_numeric($student['center'])) {
            throw new \InvalidArgumentException("Invalid center: {$student['center']}");
        }
    }

    /**
     * Find eligible mentors for the student
     */
    private function findEligibleMentors(array $student): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_mentors';

        // Build WHERE clause with all required filters
        $where_conditions = [];
        $prepare_values = [];

        // Required filters
        $where_conditions[] = "gender = %s";
        $prepare_values[] = $student['gender'];

        $where_conditions[] = "center_id = %d";
        $prepare_values[] = (int) $student['center'];

        $where_conditions[] = "active = 1";
        $where_conditions[] = "(capacity - assigned) > 0";

        // Optional group/grade filter
        if (!empty($student['group_code'])) {
            $where_conditions[] = "group_code = %s";
            $prepare_values[] = $student['group_code'];
        }

        // Optional target manager filter
        if (!empty($student['target_manager_id'])) {
            $where_conditions[] = "manager_id = %d";
            $prepare_values[] = (int) $student['target_manager_id'];
        }

        // Build SQL query
        $sql = $wpdb->prepare(
            "SELECT mentor_id, name, capacity, assigned, center_id, gender, active, 
                    group_code, manager_id, allocations_new
             FROM {$table}
             WHERE " . implode(' AND ', $where_conditions) . "
             ORDER BY mentor_id",
            ...$prepare_values
        );

        $results = $wpdb->get_results($sql, 'ARRAY_A');
        
        if (!$results) {
            $this->logger->info('No eligible mentors found', [
                'student_gender' => $student['gender'],
                'student_center' => $student['center'],
                'student_group' => $student['group_code'] ?? 'not_specified',
                'target_manager' => $student['target_manager_id'] ?? 'not_specified'
            ]);
            return [];
        }

        // Convert to array and add calculated fields
        $mentors = [];
        foreach ($results as $mentor) {
            $mentor['available_capacity'] = $mentor['capacity'] - $mentor['assigned'];
            $mentor['occupancy_ratio'] = $mentor['capacity'] > 0 ? $mentor['assigned'] / $mentor['capacity'] : 1.0;
            $mentors[] = $mentor;
        }

        $this->logger->info('Eligible mentors found', [
            'count' => count($mentors),
            'filters_applied' => [
                'gender' => $student['gender'],
                'center' => $student['center'],
                'group' => $student['group_code'] ?? 'not_specified',
                'manager' => $student['target_manager_id'] ?? 'not_specified'
            ]
        ]);

        return $mentors;
    }

    /**
     * Rank candidates by priority
     */
    private function rankCandidates(array $candidates, array $student): array
    {
        // Calculate school match scores
        foreach ($candidates as &$candidate) {
            $candidate['school_match_score'] = $this->calculateSchoolMatchScore(
                $candidate['center_id'],
                $student['school_code']
            );
        }

        // Sort by priority: occupancy_ratio ASC → allocations_new ASC → mentor_id ASC
        usort($candidates, function($a, $b) {
            // First priority: occupancy ratio (ascending - prefer less occupied)
            if (abs($a['occupancy_ratio'] - $b['occupancy_ratio']) > 0.01) {
                return $a['occupancy_ratio'] <=> $b['occupancy_ratio'];
            }

            // Second priority: new allocations (ascending - prefer fewer new allocations)
            $aNew = $a['allocations_new'] ?? 0;
            $bNew = $b['allocations_new'] ?? 0;
            if ($aNew !== $bNew) {
                return $aNew <=> $bNew;
            }

            // Third priority: mentor ID (ascending - deterministic)
            return $a['mentor_id'] <=> $b['mentor_id'];
        });

        return $candidates;
    }

    /**
     * Calculate fuzzy school match score
     */
    private function calculateSchoolMatchScore(int $centerId, $schoolCode): float
    {
        if (empty($schoolCode)) {
            return 0.0;
        }

        // Get school information from crosswalk
        try {
            $schoolInfo = $this->crosswalk->getSchoolInfo($schoolCode);
            if (!$schoolInfo) {
                return 0.0;
            }

            // Check if school is in the same center
            if ($schoolInfo['center_id'] == $centerId) {
                return 1.0; // Perfect match
            }

            // Check if school is in nearby centers
            $nearbyCenters = $this->crosswalk->getNearbyCenters($centerId);
            if (in_array($schoolInfo['center_id'], $nearbyCenters)) {
                return 0.85; // Good match
            }

            // Check city match
            $centerCity = $this->crosswalk->getCityByCenterId($centerId);
            $schoolCity = $schoolInfo['city'] ?? '';
            
            if ($centerCity && $schoolCity && $centerCity === $schoolCity) {
                return 0.75; // City match
            }

            return 0.0; // No match

        } catch (\Throwable $e) {
            $this->logger->warning('allocation.school_match_error', [
                'school_code' => $schoolCode,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Try to allocate student to a specific mentor
     */
    private function tryAllocation(array $mentor, array $student): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_mentors';

        // Use atomic update to prevent race conditions
        $sql = $wpdb->prepare(
            "UPDATE {$table} 
             SET assigned = assigned + 1,
                 allocations_new = COALESCE(allocations_new, 0) + 1,
                 last_allocation = NOW()
             WHERE mentor_id = %d 
             AND active = 1 
             AND (capacity - assigned) > 0",
            $mentor['mentor_id']
        );

        $result = $wpdb->query($sql);
        
        if ($result === false || $wpdb->rows_affected === 0) {
            return ['success' => false, 'reason' => 'concurrent_allocation'];
        }

        // Record allocation history
        $this->recordAllocationHistory($mentor['mentor_id'], $student);

        // Check school match score for manual review
        $schoolMatchScore = $mentor['school_match_score'] ?? 0;
        
        if ($schoolMatchScore < self::FUZZY_ACCEPT_THRESHOLD) {
            if ($schoolMatchScore >= self::FUZZY_REVIEW_THRESHOLD) {
                $this->logger->warning('allocation.manual_review_needed', [
                    'student_id' => $student['id'] ?? 'unknown',
                    'mentor_id' => $mentor['mentor_id'],
                    'school_match_score' => $schoolMatchScore
                ]);
                
                // Add to manual review queue
                $this->addToManualReview($mentor['mentor_id'], $student, $schoolMatchScore);
            } else {
                $this->logger->warning('allocation.school_mismatch', [
                    'student_id' => $student['id'] ?? 'unknown',
                    'mentor_id' => $mentor['mentor_id'],
                    'school_match_score' => $schoolMatchScore
                ]);
                
                // Add to errors for reporting
                $this->addToErrors($mentor['mentor_id'], $student, 'school_mismatch', $schoolMatchScore);
            }
        }

        return ['success' => true];
    }

    /**
     * Record allocation history
     */
    private function recordAllocationHistory(int $mentorId, array $student): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_allocation_history';

        $wpdb->insert($table, [
            'mentor_id' => $mentorId,
            'student_id' => $student['id'] ?? 0,
            'student_name' => ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''),
            'school_code' => $student['school_code'] ?? '',
            'allocated_at' => current_time('mysql'),
            'school_match_score' => $this->calculateSchoolMatchScore(
                $this->getMentorCenterId($mentorId),
                $student['school_code']
            )
        ]);
    }

    /**
     * Add allocation to manual review queue
     */
    private function addToManualReview(int $mentorId, array $student, float $score): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_manual_review';

        $wpdb->insert($table, [
            'mentor_id' => $mentorId,
            'student_id' => $student['id'] ?? 0,
            'school_match_score' => $score,
            'created_at' => current_time('mysql'),
            'status' => 'pending'
        ]);
    }

    /**
     * Add allocation error
     */
    private function addToErrors(int $mentorId, array $student, string $errorCode, float $score): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_allocation_errors';

        $wpdb->insert($table, [
            'mentor_id' => $mentorId,
            'student_id' => $student['id'] ?? 0,
            'error_code' => $errorCode,
            'school_match_score' => $score,
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Get mentor center ID
     */
    private function getMentorCenterId(int $mentorId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_mentors';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT center_id FROM {$table} WHERE mentor_id = %d",
            $mentorId
        ));

        return (int) ($result ?: 0);
    }

    /**
     * Get allocation statistics
     */
    public function getStats(): array
    {
        global $wpdb;
        $mentorsTable = $wpdb->prefix . 'salloc_mentors';
        $historyTable = $wpdb->prefix . 'salloc_allocation_history';

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_mentors,
                SUM(capacity) as total_capacity,
                SUM(assigned) as total_assigned,
                AVG(occupancy_ratio) as avg_occupancy
             FROM {$mentorsTable}
             WHERE active = 1",
            'ARRAY_A'
        );

        $todayAllocations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$historyTable} WHERE DATE(allocated_at) = %s",
            current_time('Y-m-d')
        ));

        return [
            'total_mentors' => (int) ($stats['total_mentors'] ?? 0),
            'total_capacity' => (int) ($stats['total_capacity'] ?? 0),
            'total_assigned' => (int) ($stats['total_assigned'] ?? 0),
            'avg_occupancy' => (float) ($stats['avg_occupancy'] ?? 0),
            'today_allocations' => (int) ($todayAllocations ?? 0),
            'available_capacity' => (int) (($stats['total_capacity'] ?? 0) - ($stats['total_assigned'] ?? 0))
        ];
    }

    /**
     * Get mentor details
     */
    public function getMentorDetails(int $mentorId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_mentors';

        $mentor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE mentor_id = %d",
            $mentorId
        ), 'ARRAY_A');

        if (!$mentor) {
            return null;
        }

        // Get recent allocations
        $historyTable = $wpdb->prefix . 'salloc_allocation_history';
        $recentAllocations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$historyTable} 
             WHERE mentor_id = %d 
             ORDER BY allocated_at DESC 
             LIMIT 10",
            $mentorId
        ), 'ARRAY_A');

        $mentor['recent_allocations'] = $recentAllocations ?: [];
        $mentor['occupancy_ratio'] = $mentor['capacity'] > 0 ? $mentor['assigned'] / $mentor['capacity'] : 0;

        return $mentor;
    }

    /**
     * Reset mentor capacity (admin function)
     */
    public function resetMentorCapacity(int $mentorId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_mentors';

        $result = $wpdb->update($table, [
            'assigned' => 0,
            'allocations_new' => 0,
            'last_allocation' => null
        ], ['mentor_id' => $mentorId]);

        if ($result !== false) {
            $this->logger->info('allocation.capacity_reset', ['mentor_id' => $mentorId]);
            return true;
        }

        return false;
    }
} 
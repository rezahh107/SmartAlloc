<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Repository;

use InvalidArgumentException;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Domain\Allocation\AllocationResult;
use SmartAlloc\Domain\Allocation\AllocationStatus;

final class AllocationsRepository
{
    public function __construct(
        private LoggerInterface $logger,
        private \wpdb $wpdb
    ) {
    }

    /**
     * Persist allocation record
     *
     * @param array<int,array<string,mixed>>|null $candidates
     */
    public function save(int $entryId, string $status, ?int $mentorId = null, ?array $candidates = null, ?string $reason = null): void
    {
        if (!AllocationStatus::isValid($status)) {
            throw new InvalidArgumentException('Invalid allocation status');
        }

        $table = $this->wpdb->prefix . 'smartalloc_allocations';

        $candidatesJson = null;
        if ($candidates !== null) {
            $candidatesJson = json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } elseif ($reason !== null) {
            $candidatesJson = json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $studentHash = hash('sha256', (string) $entryId, true);

        $this->wpdb->insert($table, [
            'entry_id' => $entryId,
            'student_hash' => $studentHash,
            'status' => $status,
            'mentor_id' => $mentorId,
            'candidates' => $candidatesJson,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
    }

    /**
     * Find allocation by entry id
     *
     * @return array<string,mixed>|null
     */
    public function findByEntryId(int $entryId): ?array
    {
        $table = $this->wpdb->prefix . 'smartalloc_allocations';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT entry_id, status, mentor_id, candidates FROM {$table} WHERE entry_id = %d", $entryId),
            ARRAY_A
        );
        if (!$row) {
            return null;
        }
        if (!AllocationStatus::isValid((string) $row['status'])) {
            $this->logger->warning('allocations.invalid_status', ['entry_id' => $entryId]);
            return null;
        }
        $candidates = [];
        if (!empty($row['candidates'])) {
            try {
                $candidates = json_decode((string) $row['candidates'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning('allocations.candidates_decode_failed', ['entry_id' => $entryId]);
                $candidates = [];
            }
        }
        return [
            'entry_id' => (int) $row['entry_id'],
            'status' => (string) $row['status'],
            'mentor_id' => isset($row['mentor_id']) ? (int) $row['mentor_id'] : null,
            'candidates' => $candidates,
        ];
    }

    /**
     * Approve a manual allocation choosing a mentor.
     */
    public function approveManual(int $entryId, int $mentorId, int $reviewerId, ?string $notes = null): AllocationResult
    {
        $entryId = absint($entryId);
        $mentorId = absint($mentorId);
        $reviewerId = absint($reviewerId);

        $mentorsTable = $this->wpdb->prefix . 'salloc_mentors';
        $allocTable   = $this->wpdb->prefix . 'smartalloc_allocations';

        $this->wpdb->query('START TRANSACTION');

        $sql = $this->wpdb->prepare(
            "UPDATE {$mentorsTable} SET assigned = assigned + 1 WHERE mentor_id = %d AND assigned < capacity",
            $mentorId
        );
        $this->wpdb->query($sql);

        if ($this->wpdb->rows_affected !== 1) {
            $this->wpdb->query('ROLLBACK');
            $this->logger->warning('allocations.manual_approve_capacity', [
                'entry_id' => $entryId,
                'mentor_id' => $mentorId,
            ]);
            return new AllocationResult(['committed' => false, 'reason' => 'capacity']);
        }

        $sql = $this->wpdb->prepare(
            "UPDATE {$allocTable} SET status = %s, mentor_id = %d, reviewer_id = %d, review_notes = %s, reviewed_at = NOW() WHERE entry_id = %d AND status = %s",
            AllocationStatus::AUTO,
            $mentorId,
            $reviewerId,
            $notes,
            $entryId,
            AllocationStatus::MANUAL
        );
        $this->wpdb->query($sql);

        if ($this->wpdb->rows_affected !== 1) {
            $this->wpdb->query('ROLLBACK');
            $this->logger->warning('allocations.manual_approve_notfound', ['entry_id' => $entryId]);
            return new AllocationResult(['committed' => false, 'reason' => 'not_found']);
        }

        $this->wpdb->query('COMMIT');

        do_action('smartalloc/event', 'ManualApproved', [
            'entry_id' => $entryId,
            'mentor_id' => $mentorId,
            'reviewer_id' => $reviewerId,
        ]);

        $this->logger->info('allocations.manual_approved', [
            'entry_id' => $entryId,
            'mentor_id' => $mentorId,
            'reviewer_id' => $reviewerId,
        ]);

        return new AllocationResult(['committed' => true, 'mentor_id' => $mentorId]);
    }

    /**
     * Reject a manual allocation with a reason code.
     */
    public function rejectManual(int $entryId, int $reviewerId, string $reasonCode, ?string $notes = null): void
    {
        $entryId = absint($entryId);
        $reviewerId = absint($reviewerId);
        $reasonCode = sanitize_key($reasonCode);

        $table = $this->wpdb->prefix . 'smartalloc_allocations';

        $sql = $this->wpdb->prepare(
            "UPDATE {$table} SET status = %s, reviewer_id = %d, review_notes = %s, reviewed_at = NOW(), reason_code = %s WHERE entry_id = %d AND status = %s",
            AllocationStatus::REJECT,
            $reviewerId,
            $notes,
            $reasonCode,
            $entryId,
            AllocationStatus::MANUAL
        );
        $this->wpdb->query($sql);

        if ($this->wpdb->rows_affected === 1) {
            do_action('smartalloc/event', 'ManualRejected', [
                'entry_id' => $entryId,
                'reason_code' => $reasonCode,
                'reviewer_id' => $reviewerId,
            ]);
            $this->logger->info('allocations.manual_rejected', [
                'entry_id' => $entryId,
                'reason_code' => $reasonCode,
                'reviewer_id' => $reviewerId,
            ]);
        }
    }

    /**
     * Defer a manual allocation with optional notes.
     */
    public function deferManual(int $entryId, int $reviewerId, ?string $notes = null): bool
    {
        $entryId = absint($entryId);
        $reviewerId = absint($reviewerId);

        $table = $this->wpdb->prefix . 'smartalloc_allocations';

        $sql = $this->wpdb->prepare(
            "UPDATE {$table} SET status = %s, reviewer_id = %d, review_notes = %s, reviewed_at = NOW() WHERE entry_id = %d AND status = %s",
            AllocationStatus::DEFER,
            $reviewerId,
            $notes,
            $entryId,
            AllocationStatus::MANUAL
        );
        $this->wpdb->query($sql);

        if ($this->wpdb->rows_affected === 1) {
            do_action('smartalloc/event', 'ManualDeferred', [
                'entry_id' => $entryId,
                'reviewer_id' => $reviewerId,
            ]);
            $this->logger->info('allocations.manual_deferred', [
                'entry_id' => $entryId,
                'reviewer_id' => $reviewerId,
            ]);
            return true;
        }
        return false;
    }

    /**
     * Find manual allocations page with optional filters.
     *
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function findManualPage(int $page, int $perPage, array $filters = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $table = $this->wpdb->prefix . 'smartalloc_allocations';

        $where = ['status = %s'];
        $params = [AllocationStatus::MANUAL];

        if (!empty($filters['reason_code'])) {
            $where[] = 'reason_code = %s';
            $params[] = $filters['reason_code'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['date_to'];
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT entry_id, status, mentor_id, candidates, reviewer_id, review_notes, reviewed_at, reason_code FROM {$table} WHERE {$whereSql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $queryParams = array_merge($params, [$perPage, ($page - 1) * $perPage]);
        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $queryParams), ARRAY_A) ?: [];

        $sqlCount = "SELECT COUNT(*) FROM {$table} WHERE {$whereSql}";
        $total = (int) $this->wpdb->get_var($this->wpdb->prepare($sqlCount, $params));

        return ['rows' => $rows, 'total' => $total];
    }
}

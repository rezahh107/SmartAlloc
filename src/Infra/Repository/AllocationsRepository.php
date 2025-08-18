<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Repository;

use InvalidArgumentException;
use SmartAlloc\Contracts\LoggerInterface;
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
}

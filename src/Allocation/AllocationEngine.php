<?php

declare(strict_types=1);

namespace SmartAlloc\Allocation;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Exception\AllocationException;
use WP_Error;
use wpdb;

final class AllocationEngine {
    private const MAX_CAPACITY = 60;

    public function __construct(private wpdb $db) {}

    public function run(array $entry_ids): AllocationResult {
        do_action('smartalloc_before_allocation_run', $entry_ids);
        $results = new AllocationResult();
        foreach ($entry_ids as $entry_id) {
            $entry_id = absint($entry_id);
            if (0 === $entry_id) {
                $results->add_error($entry_id, 'invalid_entry');
                continue;
            }
            $mentor = $this->find_best_mentor($entry_id);
            $this->persist_result($entry_id, $mentor, $results);
        }
        do_action('smartalloc_after_allocation_run', $results);
        return $results;
    }

    private function find_best_mentor(int $entry_id): ?int {
        // Fuzzy match + ranking placeholders.
        return null;
    }

    private function persist_result(int $entry_id, ?int $mentor_id, AllocationResult $results): void {
        $table  = (new TableResolver($this->db))->allocations(new FormContext(0));
        $status = null === $mentor_id ? 'pending_review' : 'allocated';
        $query  = $this->db->prepare(
            "INSERT INTO {$table} (mentee_id, mentor_id, gf_entry_id, status, created_at_utc)
             VALUES (%d, %d, %d, %s, %s)",
            0,
            $mentor_id,
            $entry_id,
            $status,
            gmdate('Y-m-d H:i:s')
        );
        $this->db->query($query);
        $results->add($entry_id, $status);
    }
}

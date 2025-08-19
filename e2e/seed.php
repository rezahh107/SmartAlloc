<?php
/**
 * Seed sample data for E2E manual review tests.
 */

global $wpdb;
$candidates = json_encode([
    ['mentor_id' => 1, 'used' => 0, 'capacity' => 1],
]);
$wpdb->query(
    $wpdb->prepare(
        "INSERT INTO {$wpdb->prefix}salloc_allocations (entry_id,status,candidates) VALUES (%d,%s,%s)",
        1,
        'manual',
        $candidates
    )
);

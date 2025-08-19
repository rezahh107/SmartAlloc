<?php
/**
 * Seed sample data for E2E tests.
 */

if (!defined('ABSPATH')) {
    require_once __DIR__ . '/../../wp-load.php';
}

global $wpdb;

// Avoid duplicate seed when run multiple times.
$existing = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}salloc_allocations WHERE entry_id = %d",
        1
    )
);

if (!$existing) {
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
}

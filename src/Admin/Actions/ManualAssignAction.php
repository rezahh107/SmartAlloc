<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Actions;

use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Infra\Logger\NullLogger;

final class ManualAssignAction
{
    public static function handle(): void
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_send_json_error(['error' => 'forbidden'], 403);
        }

        check_ajax_referer('smartalloc_manual_action', 'nonce');

        $entryIds = isset($_POST['entry_ids']) ? (array) $_POST['entry_ids'] : [];
        $entryIds = array_map('absint', $entryIds);
        $mentorId = absint($_POST['mentor_id'] ?? 0);
        $notes    = isset($_POST['notes']) ? sanitize_textarea_field((string) $_POST['notes']) : null;

        if ($mentorId <= 0) {
            wp_send_json_error(['error' => 'invalid_mentor']);
        }

        $repo = apply_filters('smartalloc_allocations_repository', null);
        if (!$repo instanceof AllocationsRepository) {
            global $smartalloc_repo;
            if (is_object($smartalloc_repo)) {
                $repo = $smartalloc_repo;
            } else {
                global $wpdb;
                $repo = new AllocationsRepository(new NullLogger(), $wpdb);
            }
        }

        $reviewerId = (int) get_current_user_id();
        $results    = [];
        foreach ($entryIds as $id) {
            $res = $repo->approveManual($id, $mentorId, $reviewerId, $notes);
            $results[$id] = $res->to_array();
        }

        wp_send_json_success(['results' => $results]);
    }

    public static function candidates(): void
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_send_json_error(['error' => 'forbidden'], 403);
        }

        $repo = apply_filters('smartalloc_allocations_repository', null);
        if (!$repo instanceof AllocationsRepository) {
            global $smartalloc_repo;
            if (is_object($smartalloc_repo)) {
                $repo = $smartalloc_repo;
            } else {
                global $wpdb;
                $repo = new AllocationsRepository(new NullLogger(), $wpdb);
            }
        }

        // Simple query for available mentors
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_mentors';
        $rows  = $wpdb->get_results("SELECT mentor_id, name, assigned, capacity FROM {$table} WHERE active = 1 AND assigned < capacity LIMIT 5", ARRAY_A) ?: [];
        $out   = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['mentor_id'],
                'label' => (string) ($r['name'] ?? ('Mentor ' . $r['mentor_id'])),
                'occupancy' => (int) $r['assigned'] . '/' . (int) $r['capacity'],
            ];
        }
        wp_send_json_success($out);
    }
}

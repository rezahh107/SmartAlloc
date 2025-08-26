<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Actions;

use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Infra\Logger\NullLogger;

final class ManualAssignAction
{
    public static function handle(): void
    {
        if (!current_user_can('smartalloc_manage')) {
            wp_send_json_error(['error' => 'forbidden'], 403);
        }

        $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_STRING);
        $nonce = is_null($nonce) ? '' : wp_unslash($nonce);
        if (!wp_verify_nonce($nonce, 'smartalloc_manual_action')) {
            wp_send_json_error(['error' => 'invalid_nonce'], 400);
        }

        $entryIds = filter_input(INPUT_POST, 'entry_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $entryIds = is_array($entryIds) ? array_map('absint', wp_unslash($entryIds)) : [];
        $mentorId = filter_input(INPUT_POST, 'mentor_id', FILTER_SANITIZE_NUMBER_INT);
        $mentorId = is_null($mentorId) ? 0 : absint(wp_unslash($mentorId));
        $notesRaw = filter_input(INPUT_POST, 'notes', FILTER_UNSAFE_RAW);
        $notes    = is_null($notesRaw) ? null : sanitize_textarea_field(wp_unslash($notesRaw));

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
        if (!current_user_can('smartalloc_manage')) {
            wp_send_json_error(['error' => 'forbidden'], 403);
        }

        $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_STRING);
        $nonce = is_null($nonce) ? '' : wp_unslash($nonce);
        if (!wp_verify_nonce($nonce, 'smartalloc_manual_action')) {
            wp_send_json_error(['error' => 'invalid_nonce'], 400);
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
        // @security-ok-sql
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

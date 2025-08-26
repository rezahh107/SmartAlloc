<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Actions;

use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Infra\Logger\NullLogger;

final class ManualApproveAction
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
        $notesRaw = filter_input(INPUT_POST, 'notes', FILTER_UNSAFE_RAW);
        $notes    = is_null($notesRaw) ? null : sanitize_textarea_field(wp_unslash($notesRaw));

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
            $row = $repo->findByEntryId($id);
            $mentorId = (int) ($row['candidates'][0]['mentor_id'] ?? 0);
            if ($mentorId <= 0) {
                $results[$id] = ['committed' => false, 'reason' => 'no_candidate'];
                continue;
            }
            $res = $repo->approveManual($id, $mentorId, $reviewerId, $notes);
            $results[$id] = $res->to_array();
        }

        wp_send_json_success(['results' => $results]);
    }
}

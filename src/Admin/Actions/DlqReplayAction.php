<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Actions;

use SmartAlloc\Services\DlqService;

final class DlqReplayAction
{
    public static function register(): void
    {
        add_action('admin_post_smartalloc_dlq_replay', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        if (!current_user_can(SMARTALLOC_CAP_MANAGE)) {
            wp_die(esc_html__('Unauthorized', 'smartalloc'), 403);
        }

        check_admin_referer('smartalloc_dlq_replay');

        $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
        if (!$messageId) {
            wp_die(esc_html__('Invalid message ID', 'smartalloc'), 400);
        }

        $svc = apply_filters('smartalloc_dlq_service', null);
        if (!$svc instanceof DlqService) {
            $svc = new DlqService();
        }

        $row = $svc->get($messageId);
        if ($row) {
            $payload = [
                'event_name' => (string) $row['event_name'],
                'body'       => $row['payload'],
                '_attempt'   => 1,
            ];
            do_action('smartalloc_notify', $payload);
            $svc->delete($messageId);
            $replayed = '1';
        } else {
            $replayed = '0';
        }

        wp_redirect(add_query_arg([
            'page'     => 'smartalloc-dlq',
            'replayed' => $replayed,
        ], admin_url('admin.php')));
        exit;
    }
}

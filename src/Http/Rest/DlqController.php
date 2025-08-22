<?php
// @security-ok-rest

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use SmartAlloc\Services\DlqService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class DlqController
{
    public function __construct(private DlqService $dlq)
    {
    }

    public function register_routes(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route('smartalloc/v1', '/dlq', [
                'methods'             => 'GET',
                'permission_callback' => fn() => current_user_can(SMARTALLOC_CAP),
                'callback'            => [$this, 'list'],
            ]);
            register_rest_route('smartalloc/v1', '/dlq/(?P<id>\d+)/retry', [
                'methods'             => 'POST',
                'permission_callback' => fn() => current_user_can(SMARTALLOC_CAP),
                'callback'            => [$this, 'retry'],
                'args'                => [
                    'id' => ['sanitize_callback' => 'absint'],
                ],
            ]);
        });
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function list(WP_REST_Request $request)
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }
        $rows = $this->dlq->listRecent();
        $out = [];
        foreach ($rows as $r) {
            $preview = substr(
                (string) wp_json_encode($r['payload']),
                0,
                200
            );
            $out[] = [
                'id'         => (int) $r['id'],
                'event_name' => (string) $r['event_name'],
                'attempts'   => (int) $r['attempts'],
                'error_text' => $r['error_text'],
                'created_at' => $r['created_at'],
                'payload'    => $preview,
            ];
        }
        return new WP_REST_Response($out, 200);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function retry(WP_REST_Request $request)
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }
        $id = (int) $request->get_param('id');
        $row = $this->dlq->get($id);
        if (!$row) {
            return new WP_Error('not_found', 'Not found', ['status' => 404]);
        }
        do_action('smartalloc_notify', [
            'event_name' => (string) $row['event_name'],
            'body'       => $row['payload'],
            '_attempt'   => 1,
        ]);
        $this->dlq->delete($id);
        return new WP_REST_Response(['ok' => true], 200);
    }
}

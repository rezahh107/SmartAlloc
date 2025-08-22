<?php
// @security-ok-rest

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use SmartAlloc\Services\NotificationService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class DlqController
{
    public function __construct(private NotificationService $notifications)
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
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_dlq';
        $sql = $wpdb->prepare(
            "SELECT id, payload_json, last_error, attempts, created_at_utc FROM {$table} WHERE status=%s ORDER BY id DESC LIMIT %d",
            'ready',
            200
        );
        // @security-ok-sql
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return new WP_REST_Response($rows, 200);
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
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_dlq';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d AND status=%s", $id, 'ready'), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Not found', ['status' => 404]);
        }
        $payload = json_decode($row['payload_json'], true);
        if (!is_array($payload)) {
            return new WP_Error('invalid_payload', 'Invalid payload', ['status' => 422]);
        }
        $this->notifications->send($payload);
        $wpdb->delete($table, ['id' => $id]);
        return new WP_REST_Response(['ok' => true], 200);
    }
}

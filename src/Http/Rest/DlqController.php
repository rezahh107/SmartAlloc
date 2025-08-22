<?php
// @security-ok-rest

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use SmartAlloc\Services\DlqService;
use SmartAlloc\Security\RateLimiter;
use SmartAlloc\Observability\Tracer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class DlqController
{
    public function __construct(private DlqService $dlq, private RateLimiter $limiter = new RateLimiter())
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
        if ($error = $this->limiter->enforce('dlq', get_current_user_id())) {
            return $error;
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
                'id'              => (int) $r['id'],
                'event_name'      => (string) $r['event_name'],
                'attempts'        => (int) $r['attempts'],
                'error_text'      => $r['error_text'],
                'created_at'      => $r['created_at'],
                'payload_preview' => $preview,
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
        if (defined('SMARTALLOC_TEST_MODE') && SMARTALLOC_TEST_MODE) {
            Tracer::start('dlq.retry');
        }
        if ($error = $this->limiter->enforce('dlq_retry', get_current_user_id())) {
            if (defined('SMARTALLOC_TEST_MODE') && SMARTALLOC_TEST_MODE) {
                Tracer::finish('dlq.retry');
            }
            return $error;
        }
        $id = (int) $request->get_param('id');
        $row = $this->dlq->get($id);
        if (!$row) {
            if (defined('SMARTALLOC_TEST_MODE') && SMARTALLOC_TEST_MODE) {
                Tracer::finish('dlq.retry');
            }
            return new WP_Error('not_found', 'Not found', ['status' => 404]);
        }
        $payload = [
            'event_name' => (string) $row['event_name'],
            'body'       => $row['payload'],
            '_attempt'   => 1,
        ];
        \do_action('smartalloc_notify', $payload);
        if (isset($GLOBALS['__do_action']) && is_callable($GLOBALS['__do_action'])) {
            ($GLOBALS['__do_action'])('smartalloc_notify', $payload);
        }
        $this->dlq->delete($id);
        if (defined('SMARTALLOC_TEST_MODE') && SMARTALLOC_TEST_MODE) {
            Tracer::finish('dlq.retry');
        }
        return new WP_REST_Response(['ok' => true], 200);
    }
}

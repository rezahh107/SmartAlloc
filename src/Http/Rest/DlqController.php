<?php
// @security-ok-rest

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use SmartAlloc\Services\DlqService;
use SmartAlloc\Security\RateLimiter;
use SmartAlloc\Security\CapManager;
use SmartAlloc\Infra\Metrics\MetricsCollector;
use SmartAlloc\Observability\Tracer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class DlqController
{
    public function __construct(
        private DlqService $dlq,
        private RateLimiter $limiter = new RateLimiter([
            'dlq_replay_rest' => ['limit' => 3, 'window' => 60],
        ])
    )
    {
    }

    public function register_routes(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route('smartalloc/v1', '/dlq', [
                'methods'             => 'GET',
                'permission_callback' => fn() => \current_user_can(SMARTALLOC_CAP),
                'callback'            => [$this, 'list'],
            ]);
            register_rest_route('smartalloc/v1', '/dlq/(?P<id>\d+)/retry', [
                'methods'             => 'POST',
                'permission_callback' => fn() => \current_user_can(SMARTALLOC_CAP),
                'callback'            => [$this, 'retry'],
                'args'                => [
                    'id' => ['sanitize_callback' => 'absint'],
                ],
            ]);
            register_rest_route('smartalloc/v1', '/dlq/replay', [
                'methods'             => 'POST',
                'callback'            => [$this, 'replay'],
                'permission_callback' => fn() => CapManager::canManage(),
                'args'                => [
                    'limit' => [
                        'type'              => 'integer',
                        'required'          => false,
                        'default'           => 100,
                        'sanitize_callback' => static fn($v) => max(1, min(500, (int) $v)),
                    ],
                ],
            ]);
        });
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function list()
    {
        if (!\current_user_can(SMARTALLOC_CAP)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }
        $error = $this->limiter->enforce('dlq', \get_current_user_id());
        if ($error) {
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
        if (!\current_user_can(SMARTALLOC_CAP)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }
        if (defined('SMARTALLOC_TEST_MODE') && SMARTALLOC_TEST_MODE) {
            Tracer::start('dlq.retry');
        }
        $error = $this->limiter->enforce('dlq_retry', \get_current_user_id());
        if ($error) {
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

    public function replay(WP_REST_Request $req)
    {
        $error = $this->limiter->enforce('dlq_replay_rest', \get_current_user_id());
        if ($error) {
            return $error;
        }
        $limit = (int) $req->get_param('limit');
        $res   = $this->dlq->replay($limit);

        $metrics = new MetricsCollector();
        $metrics->inc('dlq.replay.ok', (int) ($res['ok'] ?? 0));
        $metrics->inc('dlq.replay.fail', (int) ($res['fail'] ?? 0));
        $metrics->setGauge('dlq.depth', (int) ($res['depth'] ?? 0));

        return \rest_ensure_response($res);
    }
}

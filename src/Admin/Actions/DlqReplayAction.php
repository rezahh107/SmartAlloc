<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Actions;

use SmartAlloc\Security\CapManager;
use SmartAlloc\Security\RateLimiter;
use SmartAlloc\Services\DlqService;
use SmartAlloc\Infra\Metrics\MetricsCollector;
use SmartAlloc\Support\Input;

final class DlqReplayAction
{
    public function __construct(
        private DlqService $service = new DlqService(),
        private RateLimiter $limiter = new RateLimiter([
            'dlq_replay_admin' => ['limit' => 3, 'window' => 60],
        ])
    ) {
    }

    public static function register(): void
    {
        $self = new self();
        add_action('admin_post_smartalloc_dlq_replay', [$self, 'handle']);
    }

    public function handle(): void
    {
        if (!CapManager::canManage()) {
            \wp_die(esc_html__('You are not allowed to replay DLQ.', 'smartalloc'), 403);
        }
        \check_admin_referer('smartalloc_dlq_replay');
        $error = $this->limiter->enforce('dlq_replay_admin', \get_current_user_id());
        if ($error) {
            \wp_die(esc_html($error->get_error_message()), (int) $error->get_error_data()['status']);
        }

        $limitRaw = Input::get(INPUT_POST, 'limit', FILTER_SANITIZE_NUMBER_INT);
        $limit    = $limitRaw !== null ? max(1, min(500, (int) $limitRaw)) : 100;
        $result = $this->service->replay($limit);

        $metrics = new MetricsCollector();
        $metrics->inc('dlq.replay.ok', (int) ($result['ok'] ?? 0));
        $metrics->inc('dlq.replay.fail', (int) ($result['fail'] ?? 0));
        $metrics->setGauge('dlq.depth', (int) ($result['depth'] ?? 0));

        add_settings_error(
            'smartalloc_dlq',
            'dlq_replay',
            sprintf(
                /* translators: 1: ok count, 2: fail count, 3: depth */
                esc_html__('DLQ replay done: %1$d ok, %2$d failed. Remaining depth: %3$d', 'smartalloc'),
                (int) ($result['ok'] ?? 0),
                (int) ($result['fail'] ?? 0),
                (int) ($result['depth'] ?? 0)
            ),
            'updated'
        );

        \wp_safe_redirect(\wp_get_referer() ?: \admin_url('admin.php?page=smartalloc'));
        exit;
    }
}


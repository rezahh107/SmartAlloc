<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

final class DLQMetrics
{
    private const METRIC_KEY = 'smartalloc_dlq_metrics';

    public function recordPush(string $queue, array $context = []): void
    {
        unset($context);
        $metrics = get_option(self::METRIC_KEY, []);
        $metrics[$queue]['pushes'] = ($metrics[$queue]['pushes'] ?? 0) + 1;
        $metrics[$queue]['last_push'] = gmdate('Y-m-d H:i:s');
        update_option(self::METRIC_KEY, $metrics);
    }

    public function recordFinalFailure(string $queue, array $context = []): void
    {
        $this->recordPush($queue . '_failures', $context);
        do_action('smartalloc_dlq_final_failure', $queue, $context);
    }

    public function getMetrics(): array
    {
        return get_option(self::METRIC_KEY, []);
    }
}

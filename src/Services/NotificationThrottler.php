<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

final class NotificationThrottler
{
    private const TRANSIENT_PREFIX = 'smartalloc_notify_throttle_';

    public function canSend(string $recipient, array $config = []): bool
    {
        $limit = $config['limit'] ?? (defined('SMARTALLOC_NOTIFY_LIMIT_PER_MIN') ? (int) SMARTALLOC_NOTIFY_LIMIT_PER_MIN : 10);
        $key   = self::TRANSIENT_PREFIX . md5($recipient);
        $count = (int) get_transient($key);
        return $count < $limit;
    }

    public function recordSend(string $recipient): void
    {
        $key   = self::TRANSIENT_PREFIX . md5($recipient);
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, 60);
        $stats         = get_option('smartalloc_throttle_stats', ['hits' => 0]);
        $stats['hits'] = (int) $stats['hits'] + 1;
        update_option('smartalloc_throttle_stats', $stats);
    }

    public function getThrottleStats(): array
    {
        return get_option('smartalloc_throttle_stats', ['hits' => 0]);
    }
}

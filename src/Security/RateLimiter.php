<?php

declare(strict_types=1);

namespace SmartAlloc\Security;

use WP_Error;

/**
 * Simple token bucket rate limiter using WordPress transients.
 * Configurable per-endpoint limits.
 */
final class RateLimiter
{
    /** @var array<string,array{limit:int,window:int}> */
    private array $config;

    /**
     * @param array<string,array{limit:int,window:int}>|null $config
     */
    public function __construct(?array $config = null)
    {
        $defaults = [
            'metrics'   => ['limit' => 60, 'window' => 60],
            'health'    => ['limit' => 60, 'window' => 60],
            'dlq'       => ['limit' => 30, 'window' => 60],
            'dlq_retry' => ['limit' => 10, 'window' => 60],
        ];
        if ($config !== null) {
            $this->config = array_merge($defaults, $config);
        } elseif (function_exists('apply_filters')) {
            /** @var array<string,array{limit:int,window:int}> $cfg */
            $cfg = apply_filters('smartalloc_rate_limits', $defaults);
            $this->config = array_merge($defaults, $cfg);
        } else {
            $this->config = $defaults;
        }
    }

    /**
     * Enforce rate limit for endpoint type and user.
     *
     * @return WP_Error|null
     */
    public function enforce(string $type, int $userId): ?WP_Error
    {
        $limit = $this->config[$type]['limit'] ?? 0;
        $window = $this->config[$type]['window'] ?? 60;
        $bucketKey = "rest:{$type}:{$userId}";
        [$allowed, $retry] = $this->hit($bucketKey, $limit, $window);
        if ($allowed) {
            return null;
        }
        return new WP_Error('RATE_LIMITED', 'Too many requests', [
            'status' => 429,
            'retry_after' => $retry,
        ]);
    }

    /**
     * @return array{0:bool,1:int} [allowed,retry_after]
     */
    private function hit(string $key, int $limit, int $window): array
    {
        $now = time();
        $transientKey = 'smartalloc_rl_' . md5($key);
        $bucket = get_transient($transientKey);
        if ($bucket === false || !is_array($bucket) || ($bucket['expires'] ?? 0) <= $now) {
            $bucket = ['count' => 1, 'expires' => $now + $window];
            set_transient($transientKey, $bucket, $window);
            return [true, 0];
        }
        if (($bucket['count'] ?? 0) < $limit) {
            $bucket['count']++;
            set_transient($transientKey, $bucket, $bucket['expires'] - $now);
            return [true, 0];
        }
        $retry = max(1, (int) ($bucket['expires'] - $now));
        set_transient($transientKey, $bucket, $bucket['expires'] - $now);
        return [false, $retry];
    }
}

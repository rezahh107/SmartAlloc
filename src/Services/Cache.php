<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Two-layer cache with object cache fallback.
 *
 * L1: WordPress object cache (group `smartalloc`)
 * L2: WordPress transients with prefix `smartalloc_`
 */
class Cache
{
    private const GROUP = 'smartalloc';
    private const TRANSIENT_PREFIX = 'smartalloc_';

    /**
     * Get from L1 cache. Falls back to L2 when object cache is disabled.
     */
    public function l1Get(string $key): mixed
    {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            return wp_cache_get($key, self::GROUP);
        }

        return $this->l2Get($key);
    }

    /**
     * Set value in L1 cache. Uses L2 when object cache is unavailable.
     */
    public function l1Set(string $key, mixed $value, int $ttl = 300): bool
    {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            return wp_cache_set($key, $value, self::GROUP, $ttl);
        }

        return $this->l2Set($key, $value, $ttl);
    }

    /**
     * Delete value from L1 cache. Uses L2 when object cache is unavailable.
     */
    public function l1Del(string $key): bool
    {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            return wp_cache_delete($key, self::GROUP);
        }

        return $this->l2Del($key);
    }

    /**
     * Get from L2 cache (transients).
     */
    public function l2Get(string $key): mixed
    {
        return get_transient($this->prefix($key));
    }

    /**
     * Set value in L2 cache.
     */
    public function l2Set(string $key, mixed $value, int $ttl = 600): bool
    {
        return set_transient($this->prefix($key), $value, $ttl);
    }

    /**
     * Delete value from L2 cache.
     */
    public function l2Del(string $key): bool
    {
        return delete_transient($this->prefix($key));
    }

    /**
     * Helper to add prefix for transient keys.
     */
    private function prefix(string $key): string
    {
        return self::TRANSIENT_PREFIX . $key;
    }

    /**
     * Flush all cache layers for tests only.
     */
    public function flushAllForTests(): void
    {
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        if (isset($GLOBALS['sa_transients'])) {
            foreach (array_keys($GLOBALS['sa_transients']) as $key) {
                if (str_starts_with($key, self::TRANSIENT_PREFIX)) {
                    delete_transient($key);
                }
            }
        }
    }
}

if (defined('PHPUNIT_RUNNING') && !function_exists('sa_cache_flush')) {
    function sa_cache_flush(): void
    {
        (new \SmartAlloc\Services\Cache())->flushAllForTests();
    }
}

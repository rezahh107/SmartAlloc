<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Three-layer cache service
 * L1: Object Cache (Redis/Memcached), L2: Transients, L3: Database
 *
 * @note L2 clearing scoped to `smartalloc_` transients with prepared queries.
 */
final class Cache
{
    /**
     * L1 Cache - Object Cache (Redis/Memcached)
     */
    public function l1Get(string $key): mixed
    {
        return wp_cache_get($key, 'smartalloc');
    }

    public function l1Set(string $key, mixed $value, int $ttl = 300): bool
    {
        return wp_cache_set($key, $value, 'smartalloc', $ttl);
    }

    public function l1Delete(string $key): bool
    {
        return wp_cache_delete($key, 'smartalloc');
    }

    /**
     * L2 Cache - WordPress Transients
     */
    public function l2Get(string $key): mixed
    {
        return get_transient($key);
    }

    public function l2Set(string $key, mixed $value, int $ttl = 600): bool
    {
        return set_transient($key, $value, $ttl);
    }

    public function l2Delete(string $key): bool
    {
        return delete_transient($key);
    }

    /**
     * L3 Cache - Database (for precomputed views)
     */
    public function l3Get(string $key): mixed
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_cache';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT value FROM {$table} WHERE cache_key = %s AND expires_at > NOW()",
            $key
        ));
        
        return $result ? json_decode($result, true) : null;
    }

    public function l3Set(string $key, mixed $value, int $ttl = 3600): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_cache';
        $expires = gmdate('Y-m-d H:i:s', time() + $ttl);
        
        $result = $wpdb->replace($table, [
            'cache_key' => $key,
            'value' => wp_json_encode($value),
            'expires_at' => $expires
        ]);
        
        return $result !== false;
    }

    public function l3Delete(string $key): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_cache';
        
        $result = $wpdb->delete($table, ['cache_key' => $key]);
        return $result !== false;
    }

    /**
     * Multi-layer get with fallback and health checks
     */
    public function get(string $key): mixed
    {
        // Try L1 first (Object Cache)
        if ($this->isL1Healthy()) {
            $value = $this->l1Get($key);
            if ($value !== false) {
                return $value;
            }
        }

        // Try L2 (Transients)
        if ($this->isL2Healthy()) {
            $value = $this->l2Get($key);
            if ($value !== false) {
                // Populate L1 if healthy
                if ($this->isL1Healthy()) {
                    $this->l1Set($key, $value, $this->getDefaultTTL('l1'));
                }
                return $value;
            }
        }

        // Try L3 (Database)
        $value = $this->l3Get($key);
        if ($value !== null) {
            // Populate upper layers if healthy
            if ($this->isL2Healthy()) {
                $this->l2Set($key, $value, $this->getDefaultTTL('l2'));
            }
            if ($this->isL1Healthy()) {
                $this->l1Set($key, $value, $this->getDefaultTTL('l1'));
            }
            return $value;
        }

        return null;
    }

    /**
     * Multi-layer set
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $success = true;
        
        // Set in all layers
        $success = $success && $this->l1Set($key, $value, min(300, $ttl));
        $success = $success && $this->l2Set($key, $value, min(600, $ttl));
        $success = $success && $this->l3Set($key, $value, $ttl);
        
        return $success;
    }

    /**
     * Multi-layer delete
     */
    public function delete(string $key): bool
    {
        $success = true;
        
        $success = $success && $this->l1Delete($key);
        $success = $success && $this->l2Delete($key);
        $success = $success && $this->l3Delete($key);
        
        return $success;
    }

    /**
     * Check if L1 cache (Object Cache) is healthy
     */
    private function isL1Healthy(): bool
    {
        try {
            $testKey = 'health_check_l1_' . uniqid();
            $testValue = 'ok';
            
            $setResult = $this->l1Set($testKey, $testValue, 5);
            if (!$setResult) {
                return false;
            }
            
            $getResult = $this->l1Get($testKey);
            $this->l1Delete($testKey);
            
            return $getResult === $testValue;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if L2 cache (Transients) is healthy
     */
    private function isL2Healthy(): bool
    {
        try {
            $testKey = 'health_check_l2_' . uniqid();
            $testValue = 'ok';
            
            $setResult = $this->l2Set($testKey, $testValue, 5);
            if (!$setResult) {
                return false;
            }
            
            $getResult = $this->l2Get($testKey);
            $this->l2Delete($testKey);
            
            return $getResult === $testValue;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get default TTL for a cache layer
     */
    private function getDefaultTTL(string $layer): int
    {
        $defaultTTL = apply_filters('smartalloc_cache_ttl_default', [
            'l1' => 300,   // 5 minutes
            'l2' => 600,   // 10 minutes
            'l3' => 3600   // 1 hour
        ]);
        
        return $defaultTTL[$layer] ?? 300;
    }

    /**
     * Get cache health status
     */
    public function getHealthStatus(): array
    {
        return [
            'l1' => $this->isL1Healthy(),
            'l2' => $this->isL2Healthy(),
            'l3' => true, // L3 is always available (database)
            'default_ttl' => $this->getDefaultTTL('l1')
        ];
    }

    /**
     * Clear all cache layers
     */
    public function clearAll(): bool
    {
        $success = true;
        
        if ($this->isL1Healthy()) {
            $success = $success && $this->clearL1Cache();
        }
        
        if ($this->isL2Healthy()) {
            $success = $success && $this->clearL2Cache();
        }
        
        $success = $success && $this->clearL3Cache();
        
        return $success;
    }

    /**
     * Clear L1 cache (Object Cache)
     */
    private function clearL1Cache(): bool
    {
        try {
            // This is a simplified approach - in production you might want to use cache groups
            return wp_cache_flush();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Clear L2 cache (Transients)
     */
    private function clearL2Cache(): bool
    {
        try {
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_smartalloc_%'
                )
            );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_timeout_smartalloc_%'
                )
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Clear L3 cache (Database)
     */
    private function clearL3Cache(): bool
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'salloc_cache';
            $wpdb->query("DELETE FROM {$table}");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
} 
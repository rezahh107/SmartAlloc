<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Integrate SmartAlloc checks into WordPress Site Health.
 */
final class HealthCheckService
{
    /** Register Site Health hooks. */
    public function register(): void
    {
        add_filter('site_status_tests', [$this, 'addTests']);
    }

    /**
     * Add SmartAlloc tests to Site Health.
     *
     * @param array $tests
     * @return array
     */
    public function addTests(array $tests): array
    {
        $tests['direct']['smartalloc'] = [
            'label' => function_exists('__') ? __('SmartAlloc health', 'smartalloc') : 'SmartAlloc health',
            'test'  => [$this, 'run'],
        ];
        return $tests;
    }

    /**
     * Execute SmartAlloc health checks.
     *
     * @return array{label:string,status:string,description:string}
     */
    public function run(): array
    {
        global $wpdb;
        $dbOk = (bool) ($wpdb->get_var('SELECT 1') !== null);

        $cacheOk = false;
        if (function_exists('wp_cache_get')) {
            $testKey = '__sa_health__';
            $cacheOk = wp_cache_set($testKey, '1', 'smartalloc', 1) && wp_cache_get($testKey, 'smartalloc') === '1';
        }

        $dlqTable = $wpdb->prefix . 'salloc_dlq';
        $dlqBacklog = (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$dlqTable} WHERE status='ready'") ?: 0);

        $mentorsTable = $wpdb->prefix . 'salloc_mentors';
        $overCapacity = (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$mentorsTable} WHERE assigned > 60") ?: 0);

        $status = ($dbOk && $cacheOk && $dlqBacklog === 0 && $overCapacity === 0) ? 'good' : 'critical';

        $desc  = '<ul>';
        $desc .= '<li>DB: ' . ($dbOk ? 'ok' : 'error') . '</li>';
        $desc .= '<li>Queue: ' . ($cacheOk ? 'ok' : 'error') . '</li>';
        $desc .= '<li>DLQ backlog: ' . $dlqBacklog . '</li>';
        $desc .= '<li>Mentors over capacity: ' . $overCapacity . '</li>';
        $desc .= '</ul>';

        return [
            'label'       => function_exists('__') ? __('SmartAlloc health', 'smartalloc') : 'SmartAlloc health',
            'status'      => $status,
            'description' => $desc,
        ];
    }
}

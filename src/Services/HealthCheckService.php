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
        $useI18n = function_exists('__') && apply_filters('smartalloc_use_i18n', false);
        $tests['direct']['smartalloc'] = [
            'label' => $useI18n ? __('SmartAlloc health', 'smartalloc') : 'SmartAlloc health',
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
        $dlqBacklog = (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$dlqTable}") ?: 0); // @security-ok-sql
        $dlqThreshold = (int) apply_filters('smartalloc_dlq_backlog_threshold', 100);

        $mentorsTable = $wpdb->prefix . 'salloc_mentors';
        $overCapacity = (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$mentorsTable} WHERE assigned > 60") ?: 0);

        $hasPlaywright = class_exists('Playwright\\Browser') || file_exists(ABSPATH . '/../node_modules/.bin/playwright');
        $hasCoverage   = function_exists('xdebug_info') || extension_loaded('pcov');
        $hasScheduler  = function_exists('as_enqueue_async_action');

        $status = 'good';
        if (!$dbOk || !$cacheOk || $dlqBacklog > $dlqThreshold || $overCapacity !== 0) {
            $status = 'critical';
        }

        $desc  = '<ul>';
        $desc .= '<li>DB: ' . ($dbOk ? 'ok' : 'error') . '</li>';
        $desc .= '<li>Queue: ' . ($cacheOk ? 'ok' : 'error') . '</li>';
        $desc .= '<li>DLQ backlog: ' . $dlqBacklog . '</li>';
        $desc .= '<li>Mentors over capacity: ' . $overCapacity . '</li>';
        $desc .= '<li>Playwright: ' . ($hasPlaywright ? 'ok' : 'missing') . '</li>';
        $desc .= '<li>Coverage driver: ' . ($hasCoverage ? 'ok' : 'missing') . '</li>';
        $desc .= '<li>Action Scheduler: ' . ($hasScheduler ? 'ok' : 'missing') . '</li>';
        $desc .= '</ul>';

        $useI18n = function_exists('__') && apply_filters('smartalloc_use_i18n', false);
        return [
            'label'       => $useI18n ? __('SmartAlloc health', 'smartalloc') : 'SmartAlloc health',
            'status'      => $status,
            'description' => $desc,
        ];
    }
}

<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Admin\SiteHealth;

use SmartAlloc\UTC\UTCSweepScanner;
use SmartAlloc\Runtime\UtcRuntime;

if (!class_exists(UTCSweepScanner::class)) {
    require_once dirname(__DIR__, 3) . '/scripts/utc_sweep/UTCSweepScanner.php';
}

final class UtcHealthGuard
{
    public function register(): void
    {
        add_filter('site_status_tests', [$this, 'addTest']);
    }

    /**
     * @param array $tests
     * @return array
     */
    public function addTest(array $tests): array
    {
        if (!current_user_can('manage_options')) {
            return $tests;
        }

        $useI18n = function_exists('__') && apply_filters('smartalloc_use_i18n', false);
        $tests['direct']['smartalloc_utc_guard'] = [
            'label' => $useI18n ? __('UTC Guard', 'smartalloc') : 'UTC Guard',
            'test'  => [$this, 'run'],
        ];
        return $tests;
    }

    /**
     * Execute UTC invariant checks.
     *
     * @return array{label:string,status:string,description:string}
     */
    public function run(): array
    {
        $paths = apply_filters('smartalloc_utc_guard_paths', [
            dirname(__DIR__, 3) . '/src',
            dirname(__DIR__, 3) . '/includes',
            dirname(__DIR__, 3) . '/app',
        ]);
        $paths = array_values(array_filter($paths, 'file_exists'));

        /** @phpstan-ignore-next-line */
        $scanner = new UTCSweepScanner();
        $violations = 0;
        foreach ($paths as $path) {
            /** @phpstan-ignore-next-line */
            $results = $scanner->scan($path);
            foreach ($results['items'] as $item) {
                if (str_contains($item['file'], '/src/Debug/')) {
                    continue;
                }
                $violations++;
            }
        }

        $grep = (int) trim(shell_exec("grep -R \"current_time('mysql'\" src includes app 2>/dev/null | grep -vE \"true|,\\s*1\" | grep -v \"UtcHealthGuard.php\" | wc -l") ?? '0');

        $status = 'good';
        $desc = 'All timestamp calls use UTC.';
        if ($violations > 0 || $grep > 0 || !UtcRuntime::isUtc()) {
            $status = 'critical';
            $desc = 'Non-UTC time usage detected. See docs/UTC_INVARIANT.md';
        }

        $useI18n = function_exists('__') && apply_filters('smartalloc_use_i18n', false);
        $label = $useI18n ? __('UTC Guard', 'smartalloc') : 'UTC Guard';
        return [
            'label' => $label,
            'status' => $status,
            'description' => $desc,
        ];
    }
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', function () {
        (new UtcHealthGuard())->register();
    });
}

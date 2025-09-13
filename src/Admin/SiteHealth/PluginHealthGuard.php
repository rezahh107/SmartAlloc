<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Admin\SiteHealth;

final class PluginHealthGuard
{
    public function register(): void
    {
        add_filter('site_status_tests', [$this, 'addTests']);
    }

    /**
     * @param array $tests
     * @return array
     */
    public function addTests(array $tests): array
    {
        if (!current_user_can('manage_options')) {
            return $tests;
        }

        $tests['direct']['smartalloc_roadmap_fresh'] = [
            'label' => 'SmartAlloc — Roadmap freshness',
            'test'  => [$this, 'roadmapTest'],
        ];

        return $tests;
    }

    /**
     * @return array{label:string,status:string,description:string}
     */
    public function roadmapTest(): array
    {
        $path = dirname(__DIR__, 3) . '/docs/ROADMAP-LIVE.json';
        $label = 'Roadmap live file is present and fresh';
        if (!file_exists($path)) {
            return [
                'label' => $label,
                'status' => 'critical',
                'description' => 'Roadmap file missing: docs/ROADMAP-LIVE.json',
            ];
        }
        $mtime = (int) filemtime($path);
        $age   = (time() - $mtime) / 86400.0;
        if ($age > 7) {
            return [
                'label' => $label,
                'status' => 'recommended',
                'description' => 'Roadmap is older than 7 days; please refresh milestones/KPIs.',
            ];
        }
        return [
            'label' => $label,
            'status' => 'good',
            'description' => 'Roadmap is up-to-date (<= 7 days).',
        ];
    }
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', static function () {
        (new PluginHealthGuard())->register();
    });
}


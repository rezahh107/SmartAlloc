<?php

declare(strict_types=1);

namespace SmartAlloc\Admin;

use SmartAlloc\Admin\Pages\ExportPage;
use SmartAlloc\Admin\Pages\SettingsPage;
use SmartAlloc\Admin\Pages\ReportsPage;

final class Menu
{
    public static function register(): void
    {
        add_submenu_page(
            'smartalloc-dashboard',
            esc_html__('Export', 'smartalloc'),
            esc_html__('Export', 'smartalloc'),
            SMARTALLOC_CAP,
            'smartalloc-export',
            [ExportPage::class, 'render']
        );

        add_submenu_page(
            'smartalloc-dashboard',
            esc_html__('Manual Review', 'smartalloc'),
            esc_html__('Manual Review', 'smartalloc'),
            SMARTALLOC_CAP,
            'smartalloc-manual-review',
            [\SmartAlloc\Admin\Pages\ManualReviewPage::class, 'render']
        );

        add_submenu_page(
            'smartalloc-dashboard',
            esc_html__('Settings', 'smartalloc'),
            esc_html__('Settings', 'smartalloc'),
            SMARTALLOC_CAP,
            'smartalloc-settings',
            [SettingsPage::class, 'render']
        );

        add_submenu_page(
            'smartalloc-dashboard',
            esc_html__('Reports', 'smartalloc'),
            esc_html__('Reports', 'smartalloc'),
            SMARTALLOC_CAP,
            'smartalloc-reports',
            [ReportsPage::class, 'render']
        );
    }
}

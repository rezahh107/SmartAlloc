<?php

declare(strict_types=1);

namespace SmartAlloc\Admin;

use SmartAlloc\Admin\Pages\ExportPage;

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
    }
}

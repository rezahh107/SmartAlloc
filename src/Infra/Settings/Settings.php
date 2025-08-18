<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Settings;

/**
 * Plugin settings helper.
 */
final class Settings
{
    /**
     * Get allocation mode setting.
     */
    public static function getAllocationMode(): string
    {
        $mode = $GLOBALS['smartalloc_allocation_mode'] ?? null;
        if ($mode === null && defined('SMARTALLOC_ALLOCATION_MODE')) {
            $mode = constant('SMARTALLOC_ALLOCATION_MODE');
        }
        if ($mode === null) {
            $settings = (array) get_option('smartalloc_settings', []);
            $mode = $settings['allocation_mode'] ?? 'direct';
        }
        return in_array($mode, ['direct', 'rest'], true) ? $mode : 'direct';
    }
}

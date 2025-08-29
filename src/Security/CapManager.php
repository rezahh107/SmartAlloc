<?php
declare(strict_types=1);

namespace SmartAlloc\Security;

/**
 * Centralized capability management with legacy alias support.
 */
final class CapManager
{
    public const NEW_CAP = 'smartalloc_manage';
    public const LEGACY_CAP = 'manage_smartalloc';

    /**
     * Check if legacy alias window is enabled.
     */
    public static function aliasEnabled(): bool
    {
        $enabled = function_exists('get_option')
            ? (bool) get_option('smartalloc_cap_alias_enabled', true)
            : true;

        /**
         * Filter to override alias window state.
         *
         * @param bool $enabled Current alias state.
         */
        return (bool) apply_filters('smartalloc/cap/alias_enabled', $enabled);
    }

    /**
     * Check if current user can manage SmartAlloc.
     */
    public static function canManage(): bool
    {
        if (!function_exists('current_user_can')) {
            return false;
        }

        if (current_user_can(self::NEW_CAP)) {
            return true;
        }

        return self::aliasEnabled() && current_user_can(self::LEGACY_CAP);
    }
}


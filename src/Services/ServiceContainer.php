<?php
declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Contracts\AllocationServiceInterface;
use SmartAlloc\Infra\DB\TableResolver;

/**
 * Minimal DI accessor with WP filter override for tests.
 */
final class ServiceContainer
{
    private static ?AllocationServiceInterface $allocation = null;
    private static ?ExportService $export = null;

    public static function allocation(): AllocationServiceInterface
    {
        if (!self::$allocation) {
            /** @var mixed $svc */
            $svc = apply_filters('smartalloc_service_allocation', null);
            if ($svc instanceof AllocationServiceInterface) {
                self::$allocation = $svc;
            } else {
                global $wpdb;
                self::$allocation = new AllocationService(
                    new TableResolver($wpdb)
                );
            }
        }
        return self::$allocation;
    }

    /** For tests only. */
    public static function setAllocation(AllocationServiceInterface $svc): void
    {
        self::$allocation = $svc;
    }

    /** For tests only. */
    public static function reset(): void
    {
        self::$allocation = null;
        self::$export = null;
    }

    public static function export(): ExportService
    {
        if (!self::$export) {
            /** @var mixed $svc */
            $svc = apply_filters('smartalloc_service_export', null);
            if ($svc instanceof ExportService) {
                self::$export = $svc;
            } else {
                global $wpdb;
                self::$export = new ExportService(
                    new TableResolver($wpdb)
                );
            }
        }
        return self::$export;
    }

    /** For tests only. */
    public static function setExport(ExportService $svc): void
    {
        self::$export = $svc;
    }
}

<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\FS;

use SmartAlloc\Support\LogHelper;

final class Filesystem
{
    private static function uploadsBase(): string
    {
        if (function_exists('wp_upload_dir')) {
            $u = wp_upload_dir();
            return rtrim((string) ($u['basedir'] ?? sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'smartalloc' . DIRECTORY_SEPARATOR;
        }
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'smartalloc' . DIRECTORY_SEPARATOR;
    }

    private static function ensureDir(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
            @mkdir($dir, 0777, true);
        }
    }

    private static function isAllowed(string $path): bool
    {
        $allowed = self::uploadsBase();
        return str_starts_with($path, $allowed) || str_starts_with($path, sys_get_temp_dir());
    }

    public static function write(string $path, string $contents): bool
    {
        if (!self::isAllowed($path)) {
            LogHelper::error('filesystem.write.outside_uploads', ['path' => $path]);
        }
        self::ensureDir($path);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return @file_put_contents($path, $contents) !== false;
    }

    public static function clear(string $path): bool
    {
        return self::write($path, '');
    }

    public static function delete(string $path): bool
    {
        if (!self::isAllowed($path)) {
            LogHelper::error('filesystem.delete.outside_uploads', ['path' => $path]);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
        return @unlink($path);
    }

    public static function move(string $from, string $to): bool
    {
        if (!self::isAllowed($to)) {
            LogHelper::error('filesystem.move.outside_uploads', ['to' => $to]);
        }
        self::ensureDir($to);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
        return @rename($from, $to);
    }
}


<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Upgrade;

use SmartAlloc\Services\Db;

final class MigrationRunner
{
    private const OPTION = 'smartalloc_db_version';

    public static function maybeRun(): void
    {
        $installed = get_option(self::OPTION, '0');
        if ($installed === SMARTALLOC_DB_VERSION) {
            return;
        }
        self::runMigrations($installed);
        update_option(self::OPTION, SMARTALLOC_DB_VERSION);
    }

    private static function runMigrations(string $from): void
    {
        $migrations = [
            '1.0.0' => function (): void {
                Db::migrate();
            },
        ];

        foreach ($migrations as $version => $callback) {
            if ($from === '0' || version_compare($from, $version, '<')) {
                $callback();
            }
        }
    }
}

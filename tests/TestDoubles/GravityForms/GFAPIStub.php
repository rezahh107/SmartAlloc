<?php
declare(strict_types=1);

if (!class_exists('GFAPI')) {
    class GFAPI
    {
        /** @var array<int,array> */
        private static array $entries = [];

        public static function add_entry(array $entry): int
        {
            self::$entries[] = $entry;
            return count(self::$entries);
        }

        public static function get_entry(int $id): ?array
        {
            return self::$entries[$id - 1] ?? null;
        }
    }
}


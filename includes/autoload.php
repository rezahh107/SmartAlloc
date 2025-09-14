<?php

declare(strict_types=1);

// PSR-4 Autoloader for SmartAlloc
spl_autoload_register(
    function ($class) {
        $prefix   = 'SmartAlloc\\';
        $base_dir = __DIR__ . '/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Autoloader requires dynamic path
            require_once $file;
        }
    }
);

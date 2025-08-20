<?php
declare(strict_types=1);

if (!function_exists('scan_sql_prepare')) {
    function scan_sql_prepare(string $root, string $allowlistTag = '@security-ok-sql'): array
    {
        $violations = [];
        $ignore = ['vendor', 'tests', 'dist', 'node_modules'];
        $calls = ['query(', 'get_results(', 'get_row(', 'get_col(', 'update(', 'insert(', 'delete('];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            $rel  = substr($path, strlen(rtrim($root, '/')) + 1);
            foreach ($ignore as $dir) {
                if (strpos($rel, $dir . '/') === 0 || strpos($rel, '/' . $dir . '/') !== false) {
                    continue 2;
                }
            }

            $lines = @file($path);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $i => $line) {
                $found = false;
                foreach ($calls as $call) {
                    if (strpos($line, '$wpdb->' . $call) !== false) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    continue;
                }
                $prev = $lines[$i - 1] ?? '';
                $window = $prev . $line;
                if (strpos($window, '$wpdb->prepare(') === false && strpos($window, $allowlistTag) === false) {
                    $violations[] = [
                        'file' => $rel,
                        'line' => $i + 1,
                        'snippet' => trim($line),
                    ];
                }
            }
        }

        return $violations;
    }
}

if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $allow = '@security-ok-sql';
    foreach ($argv as $arg) {
        if (strpos($arg, '--allowlist-tag=') === 0) {
            $allow = substr($arg, strlen('--allowlist-tag='));
        }
    }
    $root = dirname(__DIR__);
    $result = scan_sql_prepare($root, $allow);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SmartAlloc\Perf\Stopwatch;

$target = $argv[1] ?? '';
if (!is_file($target)) {
    fwrite(STDERR, "scenario not found\n");
    exit(1);
}

$result = Stopwatch::measure(fn() => require $target);

echo $result->durationMs;

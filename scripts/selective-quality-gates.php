#!/usr/bin/env php
<?php
declare(strict_types=1);
// phpcs:ignoreFile
// @phpstan-ignore-file

/**
 * Runs selective quality gates on staged PHP files.
 *
 * When invoked without arguments, executes both PHPCS (WordPress-Extra)
 * and PHPStan (level 5). Passing `lint` or `analyze` runs only the
 * respective tool.
 */

$mode       = $argv[1] ?? 'both';
$files      = trim(shell_exec('git diff --name-only --cached -- "*.php"'));

if ('' === $files) {
exit(0);
}

$file_list = array_filter(
explode("\n", $files),
static fn(string $f): bool => 'scripts/selective-quality-gates.php' !== $f
);

if ($file_list === []) {
exit(0);
}

if ('lint' === $mode || 'both' === $mode) {
    $phpcs = array_merge(['vendor/bin/phpcs', '--standard=WordPress-Extra'], $file_list);
    $proc = proc_open($phpcs, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
    $exit = is_resource($proc) ? proc_close($proc) : 1;
    if (0 !== $exit) {
        exit($exit);
    }
}

if ('analyze' === $mode || 'both' === $mode) {
    $phpstan = array_merge(
        ['vendor/bin/phpstan', 'analyse', '--level=5', '--no-progress'],
        $file_list
    );
    $proc = proc_open($phpstan, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
    $exit = is_resource($proc) ? proc_close($proc) : 1;
    if (0 !== $exit) {
        exit($exit);
    }
}

exit(0);

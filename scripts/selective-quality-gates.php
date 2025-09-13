#!/usr/bin/env php
<?php

/**
 * Selective Quality Gates
 *
 * Runs PHPCS and PHPStan only on staged PHP files.
 * Usage: php scripts/selective-quality-gates.php [lint|analyze]
 *        No argument runs both lint and analyze.
 */

declare(strict_types=1);

$mode       = $argv[1] ?? 'all';
$validModes = ['lint', 'analyze', 'all'];
if (!in_array($mode, $validModes, true)) {
    fwrite(STDERR, "Unknown mode '$mode'. Running all checks.\n");
    $mode = 'all';
}

// Fetch staged PHP files
$staged = [];
// Ensure Git trusts the working directory when running in containers with different UID/GID.
// Using -c safe.directory avoids needing global git config writes.
$cwd    = getcwd();
$gitCmd = 'git -c safe.directory=' . escapeshellarg($cwd);
exec($gitCmd . ' diff --cached --name-only --diff-filter=ACMR | grep "\\.php$"', $staged);
$staged = array_filter($staged, static function (string $file): bool {
    return !preg_match('/stub|mock|fixture/i', $file);
});

if (empty($staged)) {
    echo "No PHP files staged for commit.\n";
    exit(0);
}

$files = implode(' ', array_map('escapeshellarg', $staged));
$exit  = 0;

if ($mode === 'lint' || $mode === 'all') {
    echo "Running PHPCS on staged files...\n";
    passthru("vendor/bin/phpcs --standard=PSR12 $files", $lintCode);
    $exit = max($exit, $lintCode);
}

if ($mode === 'analyze' || $mode === 'all') {
    echo "Running PHPStan on staged files...\n";
    $tmpConfig = tempnam(sys_get_temp_dir(), 'phpstan') . '.neon';
    file_put_contents($tmpConfig, "parameters:\n    bootstrapFiles: []\n");
    $cfgArg   = escapeshellarg($tmpConfig);
    $autoload = escapeshellarg(__DIR__ . '/../vendor/php-stubs/wordpress-stubs/wordpress-stubs.php');
    $cmd      = "vendor/bin/phpstan analyze --level=5 --memory-limit=512M --autoload-file=$autoload -c $cfgArg $files";
    passthru($cmd, $stanCode);
    unlink($tmpConfig);
    $exit = max($exit, $stanCode);
}

exit($exit);

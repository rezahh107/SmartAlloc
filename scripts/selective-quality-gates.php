#!/usr/bin/env php
<?php

declare(strict_types=1);
// phpcs:ignoreFile

/**
 * Selective Quality Gates
 *
 * Runs PHPCS (WordPress-Extra) and PHPStan (level 5) only on staged PHP
 * files. Pass `lint` or `analyze` to run a single tool. With no argument,
 * both checks execute.
 */

$mode       = $argv[1] ?? 'all';
$validModes = array( 'lint', 'analyze', 'all' );
if ( ! in_array( $mode, $validModes, true ) ) {
	$mode = 'all';
}

exec( 'git diff --cached --name-only --diff-filter=ACMR | grep "\.php$"', $stagedFiles );
$stagedFiles = array_filter(
	$stagedFiles,
	static fn( string $f ): bool => strpos( $f, 'stubs/' ) !== 0
);

if ( empty( $stagedFiles ) ) {
	echo 'No PHP files staged for commit.' . PHP_EOL;
	exit( 0 );
}

$filesArg = implode( ' ', array_map( 'escapeshellarg', $stagedFiles ) );
$exitCode = 0;

if ( $mode === 'lint' || $mode === 'all' ) {
	passthru( "vendor/bin/phpcs --standard=WordPress-Extra {$filesArg}", $code );
	$exitCode = max( $exitCode, (int) $code );
}

if ( $mode === 'analyze' || $mode === 'all' ) {
	passthru( "vendor/bin/phpstan analyze --level=5 {$filesArg}", $code );
	$exitCode = max( $exitCode, (int) $code );
}

exit( $exitCode );

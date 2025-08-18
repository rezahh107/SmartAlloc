#!/usr/bin/env php
<?php
declare(strict_types=1);

$php = phpversion();
$wp = getenv('WP_VERSION') ?: 'unknown';
$report = "# Validation Report\n\n";
$report .= "- PHP version: {$php}\n";
$report .= "- WP version: {$wp}\n\n";

function read_tail(string $file): string {
    if (!file_exists($file)) {
        return "(no data)";
    }
    $lines = explode("\n", trim((string) file_get_contents($file)));
    $tail = array_slice($lines, -20);
    return implode("\n", $tail);
}

$sections = [
    'PHPCS'   => 'phpcs.log',
    'Psalm'   => 'psalm.log',
    'PHPUnit' => 'phpunit.log',
    'PHPStan' => 'phpstan.log',
    'Budgets' => 'budgets.log',
];

foreach ($sections as $title => $file) {
    $report .= "## {$title}\n";
    $report .= "````\n" . read_tail($file) . "\n````\n\n";
}

$report .= "## Plugin Check\n";
$report .= "````\n" . read_tail('plugin_check.txt') . "\n````\n";

if (!is_dir('artifacts')) {
    mkdir('artifacts');
}
file_put_contents('artifacts/validation_report.md', $report);

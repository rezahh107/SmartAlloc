#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Compare Clover coverage between base and head; fail on regression beyond tolerance.
 *
 * Usage: php scripts/coverage-compare.php --base=base/build/coverage.unit.xml --head=build/coverage.unit.xml --tolerance=0.5
 */

function arg(string $name, $default = null) {
    foreach ($GLOBALS['argv'] as $a) {
        if (str_starts_with($a, "--$name=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return $default;
}

function percent(string $file): float {
    if (!is_file($file)) { throw new RuntimeException("Coverage file not found: $file"); }
    $xml = @simplexml_load_file($file);
    if ($xml === false) { throw new RuntimeException("Invalid Clover XML: $file"); }
    $metrics = $xml->project->metrics ?? null;
    if (!$metrics) { throw new RuntimeException("Missing metrics in $file"); }
    $statements = (int) ($metrics['statements'] ?? 0);
    $covered    = (int) ($metrics['coveredstatements'] ?? 0);
    return $statements > 0 ? ($covered / $statements) * 100.0 : 0.0;
}

$base = (string) arg('base', 'base/build/coverage.unit.xml');
$head = (string) arg('head', 'build/coverage.unit.xml');
$tol  = (float)  arg('tolerance', '0.5');

try {
    $pBase = percent($base);
    $pHead = percent($head);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(2);
}

$diff = $pHead - $pBase; // positive means improvement
$line = sprintf(
    'Coverage (no-regression): head=%.1f%% base=%.1f%% diff=%+.1f%% (tolerance=%.1f%%)',
    $pHead, $pBase, $diff, $tol
);
echo $line, "\n";

// Append to GH summary
$summary = getenv('GITHUB_STEP_SUMMARY');
if ($summary && is_writable(dirname($summary))) {
    file_put_contents($summary, "\n### Coverage (No Regression)\n- $line\n", FILE_APPEND);
}

// Fail if regression beyond tolerance
$eps = 1e-6;
exit(($pBase - $pHead) - $tol > $eps ? 1 : 0);


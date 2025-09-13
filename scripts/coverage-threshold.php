#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Enforce a minimum coverage threshold from a Clover XML file.
 *
 * Usage: php scripts/coverage-threshold.php --file=build/coverage.unit.xml --min=60
 * Exits non-zero when below threshold. Writes a short summary to stdout.
 */

function arg(string $name, $default = null) {
    foreach ($GLOBALS['argv'] as $a) {
        if (str_starts_with($a, "--$name=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return $default;
}

$file = arg('file', 'build/coverage.unit.xml');
$min  = (float) arg('min', '60');

if (!is_file($file)) {
    fwrite(STDERR, "Coverage file not found: $file\n");
    exit(2);
}

$xml = @simplexml_load_file($file);
if ($xml === false) {
    fwrite(STDERR, "Invalid Clover XML: $file\n");
    exit(2);
}

$metrics = $xml->project->metrics ?? null;
if (!$metrics) {
    fwrite(STDERR, "Missing metrics in Clover XML\n");
    exit(2);
}

$statements = (int) ($metrics['statements'] ?? 0);
$covered    = (int) ($metrics['coveredstatements'] ?? 0);
$percent    = $statements > 0 ? ($covered / $statements) * 100.0 : 0.0;

$percentStr = number_format($percent, 1);
$minStr     = number_format($min, 1);

$line = "Coverage: $percentStr% (min $minStr%) | statements=$statements, covered=$covered";
echo $line, "\n";

// Append to GitHub step summary if available
$summary = getenv('GITHUB_STEP_SUMMARY');
if ($summary && is_writable(dirname($summary))) {
    file_put_contents($summary, "\n### Coverage\n- $line\n", FILE_APPEND);
}

exit($percent + 1e-6 < $min ? 1 : 0);


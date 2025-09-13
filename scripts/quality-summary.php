#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Summarize static analysis results (PHPStan, Psalm) and append to GH summary.
 *
 * Usage: php scripts/quality-summary.php --phpstan=out/phpstan.json --psalm=out/psalm.json
 */

function read_json(string $path): array {
    if (!is_file($path)) { return []; }
    $raw = (string) file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function arg(string $name, $default = null) {
    foreach ($GLOBALS['argv'] as $a) {
        if (str_starts_with($a, "--$name=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return $default;
}

$phpstanPath = arg('phpstan', 'out/phpstan.json');
$psalmPath   = arg('psalm',   'out/psalm.json');

$phpstan = read_json($phpstanPath);
$psalm   = read_json($psalmPath);

$stanErrors  = 0;
if (isset($phpstan['totals']['errors'])) {
    $stanErrors = (int)$phpstan['totals']['errors'];
} elseif (isset($phpstan['files'])) {
    foreach ($phpstan['files'] as $f) {
        $stanErrors += isset($f['messages']) ? count($f['messages']) : 0;
    }
}

$psalmIssues = 0;
if (isset($psalm['issues'])) {
    $psalmIssues = is_array($psalm['issues']) ? count($psalm['issues']) : 0;
}

$lines = [
    "PHPStan errors: $stanErrors",
    "Psalm issues: $psalmIssues",
];

$summary = getenv('GITHUB_STEP_SUMMARY');
if ($summary && is_writable(dirname($summary))) {
    file_put_contents($summary, "\n### Static Analysis\n- " . implode("\n- ", $lines) . "\n", FILE_APPEND);
}

echo implode("\n", $lines), "\n";

// Never fail (advisory)
exit(0);


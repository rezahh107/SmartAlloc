#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Generate a concise QA Markdown summary from various JSON artifacts.
 * Appends to GitHub Step Summary if available and writes to artifacts/qa/qa-summary.md.
 */

function read_json(string $path): array {
    if (!is_file($path)) { return []; }
    $raw = (string)file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$root = dirname(__DIR__);
$paths = [
    'rest'   => $root . '/rest-violations.json',
    'sql'    => $root . '/sql-violations.json',
    'secrets'=> $root . '/secrets.json',
    'i18n'   => $root . '/i18n-lint.json',
    'cov'    => $root . '/build/coverage.unit.xml',
    'stan'   => $root . '/out/phpstan.json',
    'psalm'  => $root . '/out/psalm.json',
];

$rest = read_json($paths['rest']);
$sql  = read_json($paths['sql']);
$sec  = read_json($paths['secrets']);
$i18n = read_json($paths['i18n']);
$stan = read_json($paths['stan']);
$pslm = read_json($paths['psalm']);

$restCnt = isset($rest['violations']) && is_array($rest['violations']) ? count($rest['violations']) : (is_array($rest) ? count($rest) : 0);
$sqlCnt  = isset($sql['counts']['violations']) ? (int)$sql['counts']['violations'] : (isset($sql['violations']) && is_array($sql['violations']) ? count($sql['violations']) : 0);
$secCnt  = isset($sec['counts']['violations']) ? (int)$sec['counts']['violations'] : (isset($sec['findings']) && is_array($sec['findings']) ? count(array_filter($sec['findings'], fn($f) => empty($f['allowlisted']))): 0);
$i18nWrong = isset($i18n['wrong_domain']) && is_array($i18n['wrong_domain']) ? count($i18n['wrong_domain']) : 0;
$i18nPlace = isset($i18n['placeholder_mismatch']) && is_array($i18n['placeholder_mismatch']) ? count($i18n['placeholder_mismatch']) : 0;

$stanErr = 0;
if (isset($stan['totals']['errors'])) { $stanErr = (int)$stan['totals']['errors']; }
elseif (isset($stan['files'])) { foreach ($stan['files'] as $f) { $stanErr += isset($f['messages']) ? count($f['messages']) : 0; } }

$psalmIssues = isset($pslm['issues']) && is_array($pslm['issues']) ? count($pslm['issues']) : 0;

$lines = [];
$lines[] = '# QA Summary';
$lines[] = '';
$lines[] = '- REST permission violations: ' . $restCnt;
$lines[] = '- SQL prepare violations: ' . $sqlCnt;
$lines[] = '- Secrets violations: ' . $secCnt;
$lines[] = '- I18N wrong domain: ' . $i18nWrong . ', placeholder mismatch: ' . $i18nPlace;
$lines[] = '- PHPStan errors: ' . $stanErr;
$lines[] = '- Psalm issues: ' . $psalmIssues;

$md = implode("\n", $lines) . "\n";
echo $md;

$outDir = $root . '/artifacts/qa';
if (!is_dir($outDir)) { @mkdir($outDir, 0777, true); }
file_put_contents($outDir . '/qa-summary.md', $md);

$summary = getenv('GITHUB_STEP_SUMMARY');
if ($summary && is_writable(dirname($summary))) {
    file_put_contents($summary, "\n" . $md . "\n", FILE_APPEND);
}

exit(0);


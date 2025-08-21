#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(0);
}

$root = dirname(__DIR__);
$dir  = $root . '/artifacts/coverage';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$outFile = $dir . '/coverage.json';

$clover   = $dir . '/clover.xml';
$existing = $dir . '/coverage.json';
$source   = null;
$linesTotal = 0;
$linesCovered = 0;
$linesPct = null;

if (is_file($clover)) {
    $xml = @simplexml_load_file($clover);
    if ($xml !== false && isset($xml->project->metrics['statements'], $xml->project->metrics['coveredstatements'])) {
        $linesTotal   = (int)$xml->project->metrics['statements'];
        $linesCovered = (int)$xml->project->metrics['coveredstatements'];
        $linesPct     = $linesTotal > 0 ? round(($linesCovered / $linesTotal) * 100, 2) : 0.0;
        $source       = 'clover.xml';
    }
} elseif (is_file($existing)) {
    $data = json_decode((string)file_get_contents($existing), true);
    if (is_array($data)) {
        $linesTotal   = (int)($data['lines_total'] ?? 0);
        $linesCovered = (int)($data['lines_covered'] ?? 0);
        if (isset($data['lines_pct']) && $data['lines_pct'] !== null) {
            $linesPct = (float)$data['lines_pct'];
        } elseif ($linesTotal > 0) {
            $linesPct = round(($linesCovered / $linesTotal) * 100, 2);
        }
        $source = 'coverage.json';
    }
}

$result = [
    'generated_at'  => gmdate('Y-m-d\TH:i:s\Z'),
    'lines_covered' => $linesCovered,
    'lines_pct'     => $linesPct,
    'lines_total'   => $linesTotal,
    'source'        => $source,
];
ksort($result);
file_put_contents($outFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$summary = 'coverage ' . ($linesPct === null ? 'null' : $linesPct . '%') . ' from ' . ($source ?? 'none');
echo $summary . "\n";

exit(0);

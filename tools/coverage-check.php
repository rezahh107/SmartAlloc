<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/coverage-check.php <clover.xml>\n");
    exit(1);
}

$minCoverage = (int) (getenv('MIN_COVERAGE') ?: 85);
$critical = [
    'src/Infra/Export' => 'ExporterService',
    'src/Http/Rest'    => 'Http/Rest',
    'src/Compat'       => 'Compat',
]; // TODO: allow lower thresholds for non-critical namespaces

$xml = new SimpleXMLElement(file_get_contents($argv[1]));
$results = [];
foreach ($xml->xpath('//file') as $file) {
    $path = (string) $file['name'];
    foreach ($critical as $dir => $label) {
        if (str_contains($path, $dir)) {
            $metrics = $file->metrics;
            $statements = (int) $metrics['statements'];
            $covered = $statements - (int) $metrics['missedstatements'];
            $results[$label]['statements'] = ($results[$label]['statements'] ?? 0) + $statements;
            $results[$label]['covered'] = ($results[$label]['covered'] ?? 0) + $covered;
        }
    }
}

$failed = false;
foreach ($results as $label => $data) {
    $coverage = $data['statements'] > 0 ? ($data['covered'] / $data['statements'] * 100) : 0.0;
    if ($coverage < $minCoverage) {
        fwrite(STDERR, sprintf("%s coverage %.2f%% is below required %d%%\n", $label, $coverage, $minCoverage));
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}


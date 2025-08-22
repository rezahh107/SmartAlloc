#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Package selected QA artifacts into a single bundle.
 * Non-deterministic zip (timestamps etc) but deterministic contents.
 */

$root  = dirname(__DIR__);
$outDir = $root . '/artifacts/qa';
@mkdir($outDir, 0777, true);
$zipPath = $outDir . '/qa-bundle.zip';

$files = [
    'artifacts/schema/schema-validate.json'   => 'schema-validate.json',
    'artifacts/coverage/coverage.json'        => 'coverage.json',
    'artifacts/security/sql-prepare.json'     => 'sql-prepare.json',
    'artifacts/security/rest-permissions.json'=> 'rest-permissions.json',
    'artifacts/security/secrets.json'         => 'secrets.json',
    'artifacts/compliance/license-audit.json' => 'license-audit.json',
    'artifacts/qa/qa-report.json'             => 'qa-report.json',
    'artifacts/qa/qa-report.html'             => 'qa-report.html',
];

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    foreach ($files as $src => $name) {
        $full = $root . '/' . $src;
        if (is_file($full)) {
            $zip->addFile($full, $name);
        } else {
            $zip->addFromString($name, json_encode(['missing' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
    $zip->close();
} else {
    // ensure file exists even if zip creation failed
    touch($zipPath);
}

echo $zipPath . PHP_EOL;
exit(0);


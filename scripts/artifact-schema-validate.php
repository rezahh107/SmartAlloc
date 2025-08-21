#!/usr/bin/env php
<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors','0');

function iso(): string { return date('c'); }
function outDir(string $p): void { if (!is_dir($p)) @mkdir($p, 0777, true); }

$root = getcwd();
$art = $root . '/artifacts';
$schemaDir = $art . '/schema';
outDir($schemaDir);

$known = [
  'coverage' => '/coverage/coverage.json',
  'manifest' => '/dist/manifest.json',
  'sbom'     => '/dist/sbom.json',
  'go'       => '/ga/GO_NO_GO.json',
  'enforcer' => '/ga/GA_ENFORCER.json',
  'qareport' => '/qa/qa-report.json',
  'pot'      => '/i18n/pot-refresh.json',
];

$warnings = []; $scanned = 0;

$verifyShape = function (string $name, array $data, string $path) use (&$warnings) {
  // Very light checks; advisory only.
  switch ($name) {
    case 'coverage':
      if (!isset($data['totals']['lines'], $data['totals']['covered'], $data['totals']['pct'])) {
        $warnings[] = "coverage.json missing totals.* at $path";
      }
      break;
    case 'manifest':
      if (!is_array($data)) $warnings[] = "manifest.json should be an array at $path";
      break;
    case 'sbom':
      if (!is_array($data)) $warnings[] = "sbom.json should be an array at $path";
      break;
    case 'qareport':
      if (!is_array($data) && !is_object($data)) $warnings[] = "qa-report.json should be object/array at $path";
      break;
    default:
      // no-op
      break;
  }
};

foreach ($known as $key => $suffix) {
  $p = $art . $suffix;
  if (is_file($p)) {
    $scanned++;
    $raw = (string)file_get_contents($p);
    $json = json_decode($raw, true);
    if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
      $warnings[] = "invalid JSON at $p: ".json_last_error_msg();
      continue;
    }
    $verifyShape($key, $json, $p);
  }
}

// Also walk any *.json under artifacts/ as generic check
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($art, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
  if (substr((string)$file, -5) !== '.json') continue;
  $scanned++;
  $raw = (string)file_get_contents((string)$file);
  $json = json_decode($raw, true);
  if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
    $warnings[] = "invalid JSON at $file: ".json_last_error_msg();
  }
}

$out = [
  'generatedAt' => iso(),
  'count' => count($warnings),
  'scanned' => $scanned,
  'warnings' => array_values(array_unique($warnings)),
];

file_put_contents($schemaDir.'/schema-validate.json', json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "[schema-validate] scanned=$scanned warnings={$out['count']} -> artifacts/schema/schema-validate.json\n";
exit(0);

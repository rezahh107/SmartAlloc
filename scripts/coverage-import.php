#!/usr/bin/env php
<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

function outDir(string $p): void { if (!is_dir($p)) @mkdir($p, 0777, true); }
function pct(int $covered, int $total): float { return $total > 0 ? round(($covered / $total) * 100, 2) : 0.0; }
function iso(): string { return date('c'); }

$argvOpts = [
  'clover:' => null,
  'json:'   => null,
];
$opt = getopt('', array_keys($argvOpts));
$clover = $opt['clover'] ?? getenv('COVERAGE_CLOVER') ?: null;
$jsonIn = $opt['json']   ?? null;

$targets = ['artifacts/coverage/coverage.json'];
$covDir  = dirname($targets[0]);
outDir($covDir);

if ($jsonIn && is_file($jsonIn)) {
  $data = json_decode((string)file_get_contents($jsonIn), true);
  if (!is_array($data)) $data = [];
  $data['source'] = $data['source'] ?? 'existing';
  $data['generatedAt'] = $data['generatedAt'] ?? iso();
  file_put_contents($targets[0], json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
  echo "[coverage-import] re-emitted existing coverage to {$targets[0]}\n";
  exit(0);
}

// resolve clover path
if (!$clover) {
  foreach (['coverage/clover.xml', 'artifacts/coverage/clover.xml', 'clover.xml'] as $cand) {
    if (is_file($cand)) { $clover = $cand; break; }
  }
}
if (!$clover || !is_file($clover)) {
  // write empty advisory coverage
  $empty = ['source'=>'none','generatedAt'=>iso(),'totals'=>['lines'=>0,'covered'=>0,'pct'=>0],'files'=>[]];
  file_put_contents($targets[0], json_encode($empty, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
  echo "[coverage-import] no clover.xml found; wrote empty coverage to {$targets[0]}\n";
  exit(0);
}

$xml = @simplexml_load_file($clover);
if (!$xml) {
  $empty = ['source'=>'clover.xml:unreadable','generatedAt'=>iso(),'totals'=>['lines'=>0,'covered'=>0,'pct'=>0],'files'=>[]];
  file_put_contents($targets[0], json_encode($empty, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
  echo "[coverage-import] unreadable clover; wrote empty coverage\n";
  exit(0);
}

// Totals can live under project->metrics or deeper; also support statements/coveredstatements.
$total = 0; $covered = 0; $filesOut = [];

$project = $xml->project ?? null;
$metricsNodes = [];
if ($project && $project->metrics) $metricsNodes[] = $project->metrics;
if ($xml->metrics) $metricsNodes[] = $xml->metrics;

$sum = function($m) use (&$total,&$covered) {
  $lv = (int)($m['lines-valid'] ?? 0);
  $lc = (int)($m['lines-covered'] ?? 0);
  $st = (int)($m['statements'] ?? 0);
  $sc = (int)($m['coveredstatements'] ?? 0);

  if ($lv > 0 || $lc > 0) { $total += $lv; $covered += $lc; }
  elseif ($st > 0 || $sc > 0) { $total += $st; $covered += $sc; }
};

foreach ($metricsNodes as $mn) $sum($mn);

// Per-file
foreach ($xml->xpath('//file') as $f) {
  $path = (string)$f['name'];
  $m = $f->metrics ?? null;
  $fl = 0; $fc = 0;
  if ($m) {
    $lv = (int)($m['lines-valid'] ?? 0);
    $lc = (int)($m['lines-covered'] ?? 0);
    $st = (int)($m['statements'] ?? 0);
    $sc = (int)($m['coveredstatements'] ?? 0);
    if ($lv>0 || $lc>0) { $fl=$lv; $fc=$lc; }
    elseif ($st>0 || $sc>0) { $fl=$st; $fc=$sc; }
  } else {
    // fallback by counting <line type="stmt" count> entries
    $lines = $f->line ?? [];
    foreach ($lines as $ln) {
      if ((string)$ln['type'] === 'stmt') {
        $fl++;
        if ((int)$ln['covered'] === 1 || (int)$ln['count'] > 0) $fc++;
      }
    }
  }
  $filesOut[] = ['path'=>$path, 'lines'=>$fl, 'covered'=>$fc, 'pct'=>pct($fc,$fl)];
}

$out = [
  'source' => 'clover.xml',
  'generatedAt' => iso(),
  'totals' => ['lines'=>$total, 'covered'=>$covered, 'pct'=>pct($covered,$total)],
  'files' => $filesOut,
];

file_put_contents($targets[0], json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
echo "[coverage-import] wrote {$targets[0]} (pct {$out['totals']['pct']}%)\n";
exit(0);

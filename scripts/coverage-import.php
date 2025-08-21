#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(0);
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

function pct(int $covered, int $total): float
{
    return $total > 0 ? round(($covered / $total) * 100, 1) : 0.0;
}

function rel_path(string $path, string $root): string
{
    $path = str_replace('\\', '/', $path);
    $root = str_replace('\\', '/', $root);
    if (str_starts_with($path, $root . '/')) {
        return substr($path, strlen($root) + 1);
    }
    return ltrim($path, '/');
}

$root = dirname(__DIR__);
$target = $root . '/artifacts/coverage/coverage.json';
ensure_dir(dirname($target));

$candidates = [];
$override = getenv('COVERAGE_INPUT');
if ($override !== false && $override !== '') {
    $cand = $override;
    if ($cand[0] !== '/' && !preg_match('/^[A-Za-z]:\\\\/', $cand)) {
        $cand = $root . '/' . ltrim($cand, '/');
    }
    if (is_file($cand)) {
        $candidates[] = $cand;
    }
}
$candidates[] = $root . '/artifacts/coverage/clover.xml';
$candidates[] = $root . '/coverage/clover.xml';
$candidates[] = $root . '/clover.xml';
$candidates[] = $root . '/artifacts/coverage/coverage.json';
$candidates[] = $root . '/coverage.json';

$out = [
    'source' => 'none',
    'generated_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
    'totals' => ['lines_total' => 0, 'lines_covered' => 0, 'pct' => 0.0],
    'files' => [],
];

foreach ($candidates as $cand) {
    if (!is_file($cand)) {
        continue;
    }
    $ext = strtolower((string)pathinfo($cand, PATHINFO_EXTENSION));
    if ($ext === 'xml') {
        $xml = @simplexml_load_file($cand);
        if ($xml instanceof SimpleXMLElement) {
            $files = [];
            $total = 0;
            $covered = 0;
            foreach ($xml->xpath('//file') as $f) {
                $path = rel_path((string)$f['name'], $root);
                $lt = 0;
                $lc = 0;
                if (isset($f->metrics)) {
                    $m = $f->metrics;
                    if (isset($m['lines-valid']) || isset($m['lines-covered'])) {
                        $lt = (int)($m['lines-valid'] ?? 0);
                        $lc = (int)($m['lines-covered'] ?? 0);
                    } else {
                        $lt = (int)($m['statements'] ?? 0);
                        $lc = (int)($m['coveredstatements'] ?? 0);
                    }
                } else {
                    foreach ($f->line ?? [] as $ln) {
                        if ((string)$ln['type'] === 'stmt') {
                            $lt++;
                            if ((int)$ln['count'] > 0 || (int)$ln['covered'] === 1) {
                                $lc++;
                            }
                        }
                    }
                }
                $files[] = [
                    'path' => $path,
                    'lines_total' => $lt,
                    'lines_covered' => $lc,
                    'pct' => pct($lc, $lt),
                ];
                $total += $lt;
                $covered += $lc;
            }
            usort($files, fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
            $out = [
                'source' => 'clover',
                'generated_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
                'totals' => [
                    'lines_total' => $total,
                    'lines_covered' => $covered,
                    'pct' => pct($covered, $total),
                ],
                'files' => $files,
            ];
        }
    } else {
        $raw = (string)file_get_contents($cand);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            $files = [];
            $tot = $data['totals'] ?? [];
            $lt = (int)($tot['lines_total'] ?? $tot['lines'] ?? 0);
            $lc = (int)($tot['lines_covered'] ?? $tot['covered'] ?? 0);
            foreach ($data['files'] ?? [] as $f) {
                $path = rel_path((string)($f['path'] ?? ($f['file'] ?? '')), $root);
                $fl = (int)($f['lines_total'] ?? $f['lines'] ?? 0);
                $fc = (int)($f['lines_covered'] ?? $f['covered'] ?? 0);
                $files[] = [
                    'path' => $path,
                    'lines_total' => $fl,
                    'lines_covered' => $fc,
                    'pct' => pct($fc, $fl),
                ];
            }
            usort($files, fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
            $out = [
                'source' => 'json',
                'generated_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
                'totals' => [
                    'lines_total' => $lt,
                    'lines_covered' => $lc,
                    'pct' => pct($lc, $lt),
                ],
                'files' => $files,
            ];
        }
    }
    if ($out['source'] !== 'none') {
        break;
    }
}

$tmp = $target . '.tmp';
file_put_contents($tmp, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . "\n");
rename($tmp, $target);

echo '[coverage-import] source=' . $out['source'] . ' pct=' . $out['totals']['pct'] . "\n";
exit(0);

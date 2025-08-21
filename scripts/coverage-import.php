#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(0);
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

/**
 * Ensure a directory exists.
 */
function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

/**
 * Compute coverage percentage.
 */
function pct(int $covered, int $total): float
{
    return $total > 0 ? round(($covered / $total) * 100, 2) : 0.0;
}

/**
 * Reduce a path to repository-relative form.
 */
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
if ($override && is_file($override)) {
    $candidates[] = $override;
}
$candidates = array_merge($candidates, [
    $root . '/artifacts/coverage/clover.xml',
    $root . '/coverage.xml',
    $root . '/clover.xml',
    $root . '/artifacts/coverage/coverage.json',
    $root . '/coverage.json',
]);

$source = 'none';
$out = [
    'source' => 'none',
    'generated_at' => date('c'),
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
            $source = 'clover';
            $total = 0;
            $covered = 0;
            $files = [];

            // sum totals
            foreach ($xml->xpath('//metrics') as $m) {
                $lt = (int)($m['lines-valid'] ?? 0);
                $lc = (int)($m['lines-covered'] ?? 0);
                $st = (int)($m['statements'] ?? 0);
                $sc = (int)($m['coveredstatements'] ?? 0);
                if ($lt > 0 || $lc > 0) {
                    $total += $lt;
                    $covered += $lc;
                } elseif ($st > 0 || $sc > 0) {
                    $total += $st;
                    $covered += $sc;
                }
            }

            foreach ($xml->xpath('//file') as $f) {
                $path = rel_path((string)$f['name'], $root);
                $m = $f->metrics ?? null;
                $lt = 0;
                $lc = 0;
                if ($m) {
                    $lv = (int)($m['lines-valid'] ?? 0);
                    $lcov = (int)($m['lines-covered'] ?? 0);
                    $st = (int)($m['statements'] ?? 0);
                    $sc = (int)($m['coveredstatements'] ?? 0);
                    if ($lv > 0 || $lcov > 0) {
                        $lt = $lv;
                        $lc = $lcov;
                    } elseif ($st > 0 || $sc > 0) {
                        $lt = $st;
                        $lc = $sc;
                    }
                } else {
                    foreach ($f->line ?? [] as $ln) {
                        if ((string)$ln['type'] === 'stmt') {
                            $lt++;
                            if ((int)$ln['covered'] === 1 || (int)$ln['count'] > 0) {
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
            }

            usort($files, fn(array $a, array $b): int => strcmp($a['path'], $b['path']));

            $out = [
                'source' => $source,
                'generated_at' => date('c'),
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
            $source = 'json';
            $tot = $data['totals'] ?? [];
            $lt = (int)($tot['lines_total'] ?? $tot['lines'] ?? 0);
            $lc = (int)($tot['lines_covered'] ?? $tot['covered'] ?? 0);
            $files = [];
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
                'source' => $source,
                'generated_at' => date('c'),
                'totals' => [
                    'lines_total' => $lt,
                    'lines_covered' => $lc,
                    'pct' => pct($lc, $lt),
                ],
                'files' => $files,
            ];
        }
    }

    if ($source !== 'none') {
        break;
    }
}

file_put_contents($target, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . "\n");
echo '[coverage-import] source=' . $source . ' pct=' . $out['totals']['pct'] . "\n";
exit(0);


#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Statically scan register_rest_route() calls and emit a deterministic report.
 * The output is advisory and always exits with 0.
 */

function rp_scan(string $root, string $allowFile, string $outFile): array
{
    $allow = rp_load_allowlist($allowFile);
    $files = rp_collect_php($root);
    $routes = [];
    $warnings = [];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        if ($src === false) {
            continue;
        }
        $offset = 0;
        while (($pos = strpos($src, 'register_rest_route', $offset)) !== false) {
            $call = rp_extract_call($src, $pos);
            if ($call === null) {
                break;
            }
            $offset = $pos + 1;
            $meta = rp_parse_call($call, $file, $src);
            if ($meta === null) {
                continue;
            }
            $routes[] = $meta;
            foreach ($meta['warnings'] as $w) {
                $warnings[] = [
                    'file' => $meta['file'],
                    'line' => $meta['line'],
                    'route' => $meta['namespace'] . $meta['route'],
                    'type' => $w,
                    'fingerprint' => sha1($meta['file'] . ':' . $meta['line'] . ':' . $meta['namespace'] . $meta['route'])
                ];
            }
        }
    }

    // allowlist public read routes
    $warnings = array_values(array_filter($warnings, function ($w) use ($allow) {
        if ($w['type'] !== 'public_unlisted') {
            return true;
        }
        return !in_array($w['route'], $allow, true);
    }));
    foreach ($routes as &$r) {
        if (!$r['warnings']) {
            continue;
        }
        $r['warnings'] = array_values(array_filter($r['warnings'], function ($w) use ($allow, $r) {
            if ($w !== 'public_unlisted') {
                return true;
            }
            return !in_array($r['namespace'] . $r['route'], $allow, true);
        }));
    }
    unset($r);

    usort($routes, function ($a, $b) {
        return [$a['file'], $a['line'], $a['route']] <=> [$b['file'], $b['line'], $b['route']];
    });
    usort($warnings, fn($a,$b) => $a['fingerprint'] <=> $b['fingerprint']);

    $mut = 0; $ro = 0;
    foreach ($warnings as $w) {
        if (str_starts_with($w['type'], 'mutating')) { $mut++; } else { $ro++; }
    }
    $summary = [
        'routes' => count($routes),
        'warnings' => count($warnings),
        'mutating_warnings' => $mut,
        'readonly_warnings' => $ro,
    ];
    ksort($summary);

    $report = [
        'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'routes' => $routes,
        'warnings' => $warnings,
        'summary' => $summary,
    ];
    ksort($report);

    if (!is_dir(dirname($outFile))) {
        @mkdir(dirname($outFile), 0777, true);
    }
    file_put_contents($outFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return $report;
}

function rp_collect_php(string $root): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $f) {
        if ($f->isFile() && substr($f->getFilename(), -4) === '.php') {
            $path = str_replace('\\', '/', $f->getPathname());
            if (strpos($path, '/tests/') !== false || strpos($path, '/vendor/') !== false) {
                continue;
            }
            $out[] = $path;
        }
    }
    sort($out);
    return $out;
}

function rp_extract_call(string $src, int $start): ?string
{
    $open = strpos($src, '(', $start);
    if ($open === false) {
        return null;
    }
    $depth = 1; $i = $open + 1; $len = strlen($src);
    while ($i < $len && $depth > 0) {
        $ch = $src[$i];
        if ($ch === '(') { $depth++; }
        elseif ($ch === ')') { $depth--; }
        $i++;
    }
    if ($depth !== 0) {
        return null;
    }
    return substr($src, $start, $i - $start);
}

function rp_parse_call(string $call, string $file, string $src): ?array
{
    if (!preg_match('/register_rest_route\s*\(\s*([\'\"])' .
        '(?P<ns>[^\'\"]+)\1\s*,\s*([\'\"])(?P<route>[^\'\"]+)\3\s*,/s', $call, $m)) {
        return null;
    }
    $ns = $m['ns'];
    $route = $m['route'];
    $argsStr = substr($call, strpos($call, $m[0]) + strlen($m[0]));
    $perm = null; $callback = null; $methods = [];
    if (preg_match('/permission_callback\s*=>\s*([^,\)]+)/s', $argsStr, $pm)) {
        $perm = trim($pm[1]);
    }
    if (preg_match('/callback\s*=>\s*([^,\)]+)/s', $argsStr, $cm)) {
        $callback = trim($cm[1]);
    }
    if (preg_match('/methods\s*=>\s*([^,\)]+)/s', $argsStr, $mm)) {
        $methods = rp_parse_methods($mm[1]);
    }
    $line = 1 + substr_count(substr($src, 0, strpos($src, $call)), "\n");
    $meta = [
        'file' => rp_rel($file),
        'line' => $line,
        'namespace' => $ns,
        'route' => $route,
        'methods' => $methods,
        'permission_callback' => $perm,
        'callback' => $callback,
        'warnings' => [],
    ];
    rp_apply_warnings($meta);
    ksort($meta);
    $meta['methods'] = array_values($meta['methods']);
    return $meta;
}

function rp_parse_methods(string $expr): array
{
    $out = [];
    $expr = strtolower($expr);
    foreach (['get','post','put','patch','delete'] as $m) {
        if (strpos($expr, $m) !== false) {
            $out[] = strtoupper($m);
        }
    }
    sort($out);
    return array_unique($out);
}

function rp_apply_warnings(array &$meta): void
{
    $methods = $meta['methods'];
    $isMut = (bool)array_intersect(['POST','PUT','PATCH','DELETE'], $methods);
    $perm = strtolower($meta['permission_callback'] ?? '');
    if ($meta['permission_callback'] === null) {
        $meta['warnings'][] = $isMut ? 'mutating_missing_permission_callback' : 'missing_permission_callback';
    }
    if (!$isMut) {
        if ($perm === '__return_true') {
            $meta['warnings'][] = 'public_unlisted';
        }
        return;
    }
    if (strpos($perm, 'current_user_can') === false) {
        $meta['warnings'][] = 'mutating_missing_capability';
    }
    if (strpos($perm, 'nonce') === false && strpos($perm, 'signature') === false) {
        $meta['warnings'][] = 'mutating_missing_nonce';
    }
}

function rp_load_allowlist(string $file): array
{
    $out = [];
    if (!is_file($file)) {
        return $out;
    }
    foreach (file($file) ?: [] as $line) {
        if (preg_match('/^-\s*(.+)$/', trim($line), $m)) {
            $out[] = trim($m[1]);
        }
    }
    return $out;
}

function rp_rel(string $path): string
{
    $root = dirname(__DIR__) . '/';
    $path = str_replace('\\', '/', $path);
    if (str_starts_with($path, $root)) {
        return substr($path, strlen($root));
    }
    return $path;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === __FILE__) {
    $opts = getopt('', ['output::','allowlist::','q']);
    $root = dirname(__DIR__);
    foreach ($argv as $i => $a) {
        if ($i === 0) { continue; }
        if ($a !== '--q' && !str_starts_with($a, '--output') && !str_starts_with($a, '--allowlist')) {
            $root = $a;
            break;
        }
    }
    $allow = $opts['allowlist'] ?? ($root . '/qa/allowlist/rest-permissions.yml');
    $out = $opts['output'] ?? ($root . '/artifacts/security/rest-permissions.json');
    $report = rp_scan($root, $allow, $out);
    if (!isset($opts['q'])) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    exit(0);
}

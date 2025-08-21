#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$pluginFile = $root . '/smart-alloc.php';
$expectedDomain = '';
if (is_file($pluginFile)) {
    $content = (string) file_get_contents($pluginFile);
    if (preg_match('/Text Domain:\s*(\S+)/i', $content, $m)) {
        $expectedDomain = trim($m[1]);
    }
}

$files = collectPhpFiles($root);
$result = [
    'total_calls' => 0,
    'wrong_domain' => [],
    'placeholder_mismatch' => [],
    'files_scanned' => count($files),
];

$functions = [
    '__' => ['domain' => 1, 'strings' => [0]],
    '_e' => ['domain' => 1, 'strings' => [0]],
    '_x' => ['domain' => 2, 'strings' => [0]],
    '_ex' => ['domain' => 2, 'strings' => [0]],
    '_n' => ['domain' => 3, 'strings' => [0, 1]],
    '_nx' => ['domain' => 4, 'strings' => [0, 1]],
    'esc_html__' => ['domain' => 1, 'strings' => [0]],
    'esc_html_e' => ['domain' => 1, 'strings' => [0]],
    'esc_html_x' => ['domain' => 2, 'strings' => [0]],
    'esc_attr__' => ['domain' => 1, 'strings' => [0]],
    'esc_attr_e' => ['domain' => 1, 'strings' => [0]],
    'esc_attr_x' => ['domain' => 2, 'strings' => [0]],
];

foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    $tokens = token_get_all($src);
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_STRING && isset($functions[$t[1]])) {
            $call = extractCall($tokens, $i);
            if ($call === null) {
                continue;
            }
            $meta = $functions[$t[1]];
            $args = splitArgs($call['args']);
            $result['total_calls']++;
            $domainIdx = $meta['domain'];
            $domainArg = $args[$domainIdx] ?? null;
            if ($domainArg && preg_match("/['\"]([^'\"]+)['\"]/", $domainArg, $dm)) {
                $found = trim($dm[1]);
                if ($expectedDomain !== '' && $found !== $expectedDomain) {
                    $result['wrong_domain'][] = [
                        'file' => $file,
                        'line' => $call['line'],
                        'found' => $found,
                    ];
                }
            }
            foreach ($meta['strings'] as $idx) {
                $arg = $args[$idx] ?? null;
                if ($arg && preg_match("/^['\"](.*)['\"]$/s", trim($arg), $sm)) {
                    $str = stripcslashes($sm[1]);
                    if (hasPlaceholderMismatch($str)) {
                        $result['placeholder_mismatch'][] = [
                            'file' => $file,
                            'line' => $call['line'],
                            'string' => $str,
                        ];
                    }
                }
            }
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(0);

function collectPhpFiles(string $root): array
{
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $out = [];
    foreach ($rii as $f) {
        if (
            $f->isFile() &&
            substr($f->getFilename(), -4) === '.php' &&
            strpos($f->getPathname(), '/vendor/') === false &&
            strpos($f->getPathname(), '/node_modules/') === false &&
            strpos($f->getPathname(), '/tests/') === false &&
            strpos($f->getPathname(), '/scripts/') === false
        ) {
            $out[] = $f->getPathname();
        }
    }
    return $out;
}

function extractCall(array $tokens, int $idx): ?array
{
    $line = is_array($tokens[$idx]) ? $tokens[$idx][2] : 0;
    $i = $idx + 1;
    $count = count($tokens);
    while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
        $i++;
    }
    if ($i >= $count || $tokens[$i] !== '(') {
        return null;
    }
    $depth = 0;
    $call = '';
    for ($j = $i; $j < $count; $j++) {
        $tok = $tokens[$j];
        $call .= is_array($tok) ? $tok[1] : $tok;
        if ($tok === '(') {
            $depth++;
        } elseif ($tok === ')') {
            $depth--;
            if ($depth === 0) {
                break;
            }
        }
    }
    $inside = substr($call, 1, -1);
    return ['args' => $inside, 'line' => $line];
}

function splitArgs(string $args): array
{
    $out = [];
    $depth = 0;
    $current = '';
    $len = strlen($args);
    for ($i = 0; $i < $len; $i++) {
        $ch = $args[$i];
        if ($ch === '(') {
            $depth++;
        } elseif ($ch === ')') {
            $depth--;
        } elseif ($ch === ',' && $depth === 0) {
            $out[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $ch;
    }
    if (trim($current) !== '') {
        $out[] = trim($current);
    }
    return $out;
}

function hasPlaceholderMismatch(string $str): bool
{
    $count = preg_match_all('/%([0-9]+\$)?[bcdeEfFgGosuxX]/', $str, $m);
    if ($count < 2) {
        return false;
    }
    $numbers = array_filter($m[1], fn($v) => $v !== '');
    $hasNumbered = !empty($numbers);
    $hasUnnumbered = $count > count($numbers);
    if ($hasNumbered && $hasUnnumbered) {
        return true;
    }
    if ($hasNumbered) {
        $nums = array_map('intval', $numbers);
        sort($nums);
        for ($i = 0; $i < count($nums); $i++) {
            if ($nums[$i] !== $i + 1) {
                return true;
            }
        }
    }
    return false;
}

#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$pluginFile = $root . '/smart-alloc.php';
$domain = '';
if (is_file($pluginFile)) {
    $content = (string) file_get_contents($pluginFile);
    if (preg_match('/Text Domain:\s*(\S+)/i', $content, $m)) {
        $domain = trim($m[1]);
    }
}
$potFile = $root . '/languages/' . $domain . '.pot';

if (!is_file($potFile)) {
    echo json_encode(['pot_missing' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$sourceStrings = collectStrings($root);
$potStrings = parsePot($potFile);
$missing = array_values(array_diff($sourceStrings, $potStrings));
$extraneous = array_values(array_diff($potStrings, $sourceStrings));
$result = [
    'source_total' => count($sourceStrings),
    'pot_total' => count($potStrings),
    'missing' => $missing,
    'extraneous' => $extraneous,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

$dir = $root . '/artifacts/i18n';
@mkdir($dir, 0777, true);
$md = "# POT Diff\n\n";
$md .= 'Source strings: ' . count($sourceStrings) . "\n";
$md .= 'POT strings: ' . count($potStrings) . "\n";
if ($missing) {
    $md .= "\n## Missing\n";
    foreach ($missing as $s) { $md .= '- ' . $s . "\n"; }
}
if ($extraneous) {
    $md .= "\n## Extraneous\n";
    foreach ($extraneous as $s) { $md .= '- ' . $s . "\n"; }
}
@file_put_contents($dir . '/pot-diff.md', $md);

exit(0);

function collectStrings(string $root): array
{
    $files = collectPhpFiles($root);
    $strings = [];
    $map = [
        '__' => ['strings' => [0]],
        '_e' => ['strings' => [0]],
        '_x' => ['strings' => [0]],
        '_ex' => ['strings' => [0]],
        '_n' => ['strings' => [0,1]],
        '_nx' => ['strings' => [0,1]],
        'esc_html__' => ['strings' => [0]],
        'esc_html_e' => ['strings' => [0]],
        'esc_html_x' => ['strings' => [0]],
        'esc_attr__' => ['strings' => [0]],
        'esc_attr_e' => ['strings' => [0]],
        'esc_attr_x' => ['strings' => [0]],
    ];
    foreach ($files as $file) {
        $src = (string) file_get_contents($file);
        $tokens = token_get_all($src);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && $t[0] === T_STRING && isset($map[$t[1]])) {
                $call = extractCall($tokens, $i);
                if ($call === null) { continue; }
                $args = splitArgs($call['args']);
                foreach ($map[$t[1]]['strings'] as $idx) {
                    $arg = $args[$idx] ?? null;
                    if ($arg && preg_match("/^['\"](.*)['\"]$/s", trim($arg), $m)) {
                        $strings[] = stripcslashes($m[1]);
                    }
                }
            }
        }
    }
    return array_values(array_unique($strings));
}

function parsePot(string $file): array
{
    $lines = file($file) ?: [];
    $strings = [];
    $collect = false;
    $current = '';
    foreach ($lines as $line) {
        $line = rtrim($line, "\r\n");
        if (strpos($line, 'msgid "') === 0) {
            $collect = true;
            $current = stripcslashes(substr($line, 7, -1));
            continue;
        }
        if ($collect) {
            if ($line !== '' && $line[0] === '"') {
                $current .= stripcslashes(substr($line, 1, -1));
            } else {
                if ($current !== '') { $strings[] = $current; }
                $collect = false;
                $current = '';
            }
        }
    }
    if ($collect && $current !== '') { $strings[] = $current; }
    return array_values(array_unique($strings));
}

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
            if ($depth === 0) { break; }
        }
    }
    return ['args' => substr($call, 1, -1)];
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

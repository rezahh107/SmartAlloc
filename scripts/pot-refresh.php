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

$files = collectPhpFiles($root);
$map = [
    '__' => ['singular' => 0, 'domain' => 1],
    '_e' => ['singular' => 0, 'domain' => 1],
    '_x' => ['singular' => 0, 'context' => 1, 'domain' => 2],
    '_ex' => ['singular' => 0, 'context' => 1, 'domain' => 2],
    '_n' => ['singular' => 0, 'plural' => 1, 'domain' => 3],
    '_nx' => ['singular' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4],
    'esc_html__' => ['singular' => 0, 'domain' => 1],
    'esc_html_e' => ['singular' => 0, 'domain' => 1],
    'esc_html_x' => ['singular' => 0, 'context' => 1, 'domain' => 2],
    'esc_attr__' => ['singular' => 0, 'domain' => 1],
    'esc_attr_e' => ['singular' => 0, 'domain' => 1],
    'esc_attr_x' => ['singular' => 0, 'context' => 1, 'domain' => 2],
];

$entries = [];
$domainMismatch = 0;
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
            $spec = $map[$t[1]];
            $domainArg = $args[$spec['domain']] ?? null;
            $domainVal = stringArg($domainArg);
            if ($domainVal !== $domain) {
                $domainMismatch++;
                continue;
            }
            $singular = stringArg($args[$spec['singular']] ?? null);
            if ($singular === null) { continue; }
            $plural = isset($spec['plural']) ? stringArg($args[$spec['plural']] ?? null) : null;
            $context = isset($spec['context']) ? stringArg($args[$spec['context']] ?? null) : null;
            $key = json_encode([$context, $singular, $plural]);
            $entries[$key] = ['ctx' => $context, 'msgid' => $singular, 'plural' => $plural];
        }
    }
}
ksort($entries);

$dir = $root . '/artifacts/i18n';
@mkdir($dir, 0777, true);

$pot = [];
$pot[] = 'msgid ""';
$pot[] = 'msgstr ""';
$pot[] = '"Content-Type: text/plain; charset=UTF-8\\n"';
$pot[] = '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"';
$pot[] = '';
foreach ($entries as $e) {
    if ($e['ctx'] !== null) {
        $pot[] = 'msgctxt "' . potEscape($e['ctx']) . '"';
    }
    $pot[] = 'msgid "' . potEscape($e['msgid']) . '"';
    if ($e['plural'] !== null) {
        $pot[] = 'msgid_plural "' . potEscape($e['plural']) . '"';
        $pot[] = 'msgstr[0] ""';
        $pot[] = 'msgstr[1] ""';
    } else {
        $pot[] = 'msgstr ""';
    }
    $pot[] = '';
}
file_put_contents($dir . '/messages.pot', implode("\n", $pot));

$meta = [
    'pot_entries' => count($entries),
    'domain_mismatch' => $domainMismatch,
];
file_put_contents($dir . '/pot-refresh.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo 'pot-refresh: entries=' . count($entries) . ' domain_mismatch=' . $domainMismatch . PHP_EOL;
exit(0);

function collectPhpFiles(string $root): array
{
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $out = [];
    foreach ($rii as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
            $path = $f->getPathname();
            if (
                strpos($path, '/vendor/') !== false ||
                strpos($path, '/node_modules/') !== false ||
                strpos($path, '/dist/') !== false ||
                strpos($path, '/tests/') !== false ||
                strpos($path, '/artifacts/') !== false
            ) {
                continue;
            }
            $out[] = $path;
        }
    }
    sort($out);
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

function stringArg(?string $arg): ?string
{
    if ($arg === null) { return null; }
    $arg = trim($arg);
    if (preg_match("/^['\"](.*)['\"]$/s", $arg, $m)) {
        return stripcslashes($m[1]);
    }
    return null;
}

function potEscape(string $str): string
{
    return addcslashes($str, "\0..\37\\\"");
}

#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$domain = 'smartalloc';

$files = collectPhpFiles($root);
$map = [
    '__' => ['singular' => 0, 'domain' => 1],
    '_e' => ['singular' => 0, 'domain' => 1],
    '_x' => ['singular' => 0, 'context' => 1, 'domain' => 2],
    '_ex' => ['singular' => 0, 'context' => 1, 'domain' => 2],
    '_n' => ['singular' => 0, 'plural' => 1, 'domain' => 3],
    '_nx' => ['singular' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4],
    'esc_html__' => ['singular' => 0, 'domain' => 1],
    'esc_attr__' => ['singular' => 0, 'domain' => 1],
    'translate_nooped_plural' => ['noop' => 0, 'domain' => 2],
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
            if ($call === null) {
                continue;
            }
            $args = splitArgs($call['args']);
            $spec = $map[$t[1]];

            if ($t[1] === 'translate_nooped_plural') {
                $domainArg = $args[$spec['domain']] ?? null;
                $domainVal = stringArg($domainArg);
                if ($domainVal !== $domain) {
                    $domainMismatch++;
                    continue;
                }
                $noop = parseNooped($args[$spec['noop']] ?? null);
                if ($noop === null) {
                    continue;
                }
                $key = json_encode([$noop['context'], $noop['singular'], $noop['plural']]);
                $entries[$key] = [
                    'ctx' => $noop['context'],
                    'msgid' => $noop['singular'],
                    'plural' => $noop['plural'],
                ];
                continue;
            }

            $domainArg = $args[$spec['domain']] ?? null;
            $domainVal = stringArg($domainArg);
            if ($domainVal !== $domain) {
                $domainMismatch++;
                continue;
            }
            $singular = stringArg($args[$spec['singular']] ?? null);
            if ($singular === null) {
                continue;
            }
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
$headers = [
    'Project-Id-Version: SmartAlloc',
    'Report-Msgid-Bugs-To: ',
    'POT-Creation-Date: 2024-01-01 00:00+0000',
    'PO-Revision-Date: 2024-01-01 00:00+0000',
    'Last-Translator: ',
    'Language-Team: ',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'Plural-Forms: nplurals=2; plural=(n != 1);',
    'X-Generator: pot-refresh',
    'X-Copyright: 2024',
];
foreach ($headers as $h) {
    $pot[] = '"' . $h . '\\n"';
}
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
file_put_contents($dir . '/messages.pot', implode("\n", $pot) . "\n");

$meta = [
    'pot_entries' => count($entries),
    'domain_mismatch' => $domainMismatch,
    'files_scanned' => count($files),
];
file_put_contents($dir . '/pot-refresh.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo 'pot-refresh: pot_entries=' . count($entries) . ' domain_mismatch=' . $domainMismatch . ' files_scanned=' . count($files) . PHP_EOL;
exit(0);

function collectPhpFiles(string $root): array {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $out = [];
    foreach ($rii as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
            $path = $f->getPathname();
            if (
                str_contains($path, '/vendor/') ||
                str_contains($path, '/node_modules/') ||
                str_contains($path, '/dist/') ||
                str_contains($path, '/artifacts/') ||
                str_contains($path, '/tests/') ||
                str_contains($path, '/.wordpress-org/')
            ) {
                continue;
            }
            $out[] = $path;
        }
    }
    sort($out);
    return $out;
}

function extractCall(array $tokens, int $idx): ?array {
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
    return ['args' => substr($call, 1, -1)];
}

function splitArgs(string $args): array {
    $out = [];
    $depth = 0;
    $current = '';
    $len = strlen($args);
    for ($i = 0; $i < $len; $i++) {
        $ch = $args[$i];
        if ($ch === '(' || $ch === '[') {
            $depth++;
        } elseif ($ch === ')' || $ch === ']') {
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

function stringArg(?string $arg): ?string {
    if ($arg === null) {
        return null;
    }
    $arg = trim($arg);
    if (preg_match("/^['\"](.*)['\"]$/s", $arg, $m)) {
        return stripcslashes($m[1]);
    }
    return null;
}

function parseNooped(?string $arg): ?array {
    if ($arg === null) {
        return null;
    }
    $arg = trim($arg);
    if (str_starts_with($arg, '[') && str_ends_with($arg, ']')) {
        $inner = substr($arg, 1, -1);
    } elseif (str_starts_with(strtolower($arg), 'array(') && str_ends_with($arg, ')')) {
        $inner = substr($arg, 6, -1);
    } else {
        return null;
    }
    $parts = splitArgs($inner);
    $singular = stringArg($parts[0] ?? null);
    $plural = stringArg($parts[1] ?? null);
    $context = stringArg($parts[2] ?? null);
    if ($singular === null || $plural === null) {
        return null;
    }
    return ['singular' => $singular, 'plural' => $plural, 'context' => $context];
}

function potEscape(string $str): string {
    return addcslashes($str, "\0..\37\\\"");
}

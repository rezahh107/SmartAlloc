<?php
declare(strict_types=1);

// bin/docs_build.php — Minimal docs bundler (no external deps).
// Usage: php bin/docs_build.php --in=docs/Docs-Bundle.md --out=docs/Docs-Bundle.compiled.md
// Guarantees: never non-zero exit on missing optional tools; no network; no binaries.

$args = ['in' => 'docs/Docs-Bundle.md', 'out' => 'docs/Docs-Bundle.compiled.md'];
foreach ($argv as $a) {
    if (str_starts_with($a, '--in='))  { $args['in']  = substr($a, 5); }
    if (str_starts_with($a, '--out=')) { $args['out'] = substr($a, 6); }
}

function readFileSafe(string $path): string {
    if (!is_file($path)) {
        fwrite(STDERR, "[docs] warn: missing file: {$path}\n");
        return "<!-- missing: {$path} -->\n";
    }
    $contents = file_get_contents($path);
    return $contents === false ? "" : $contents;
}

function resolveIncludes(string $md, string $cwd, int $depth = 0): string {
    if ($depth > 20) return $md; // prevent cycles
    return preg_replace_callback('/^@include\s+(.+)$/m', function ($m) use ($cwd, $depth) {
        $rel = trim($m[1]);
        $path = str_starts_with($rel, '/') ? $rel : rtrim($cwd, '/').'/'.$rel;
        $path = realpath($path) ?: $path;
        if (!is_file($path)) {
            return "<!-- missing: {$rel} -->\n";
        }
        $dir = dirname($path);
        $content = readFileSafe($path);
        return resolveIncludes($content, $dir, $depth + 1);
    }, $md);
}

@mkdir(dirname($args['out']), 0777, true);
$tpl = readFileSafe($args['in']);
$compiled = resolveIncludes($tpl, dirname($args['in']));
// Normalize newlines
$compiled = preg_replace("/\r\n?/", "\n", $compiled);
file_put_contents($args['out'], $compiled);
fwrite(STDOUT, "[docs] compiled: {$args['out']}\n");
exit(0);

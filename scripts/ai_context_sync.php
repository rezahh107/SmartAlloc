<?php
declare(strict_types=1);

/**
 * Build ai_context.json by scanning docs/architecture/decisions for ADRs.
 * Extract title from first markdown heading; date from filename prefix YYYYMMDD if present.
 */
$root = dirname(__DIR__);
$adrDir = $root . '/docs/architecture/decisions';
$out   = $root . '/ai_context.json';

$decisions = [];
if (is_dir($adrDir)) {
    foreach (new DirectoryIterator($adrDir) as $f) {
        if ($f->isDot() || !$f->isFile()) continue;
        $name = $f->getFilename();
        if (!preg_match('/\.md$/i', $name)) continue;

        $path = $f->getPathname();
        $content = @file_get_contents($path) ?: '';
        $title = null;
        if (preg_match('/^#\s*(.+)$/m', $content, $m)) {
            $title = trim($m[1]);
        }
        $date = null;
        if (preg_match('/^(\d{8})[_-]/', $name, $m)) {
            $y = substr($m[1], 0, 4);
            $mo= substr($m[1], 4, 2);
            $d = substr($m[1], 6, 2);
            $date = "$y-$mo-$d";
        }

        $decisions[] = [
            'file'  => "docs/architecture/decisions/$name",
            'title' => $title ?: pathinfo($name, PATHINFO_FILENAME),
            'date'  => $date,
        ];
    }
}

$result = [
    'last_updated_utc' => gmdate('Y-m-d\TH:i:s\Z'),
    'decisions'        => array_values($decisions),
    'notes'            => [
        'source' => 'ADR markdown files',
        'policy' => 'No auto-commit; artifacts uploaded in CI'
    ],
];

file_put_contents($out, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Generated ai_context.json with " . count($decisions) . " decisions\n";


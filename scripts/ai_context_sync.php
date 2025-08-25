<?php
declare(strict_types=1);

/**
 * Build ai_context.json by scanning docs/architecture/decisions for ADRs.
 * Title: first markdown H1 (#). Date: YYYYMMDD prefix in filename if present.
 * Never fails CI; outputs empty list when none found.
 */
$root  = dirname(__DIR__);
$adrDir = $root . '/docs/architecture/decisions';
$out    = $root . '/ai_context.json';

$decisions = [];
if (is_dir($adrDir)) {
    foreach (new DirectoryIterator($adrDir) as $f) {
        if ($f->isDot() || !$f->isFile()) continue;
        $name = $f->getFilename();
        if (!preg_match('/\.md$/i', $name)) continue;

        $path = $f->getPathname();
        $title = '';
        $date  = null;

        $content = @file_get_contents($path) ?: '';
        if (preg_match('/^#\s+(.+)\R/m', $content, $m)) {
            $title = trim($m[1]);
        }
        if (preg_match('/^(\d{8})[_-]/', $name, $m)) {
            $d = $m[1];
            $date = substr($d,0,4) . '-' . substr($d,4,2) . '-' . substr($d,6,2);
        }

        $decisions[] = [
            'file'  => 'docs/architecture/decisions/' . $name,
            'title' => $title !== '' ? $title : pathinfo($name, PATHINFO_FILENAME),
            'date'  => $date,
            'slug'  => strtolower(preg_replace('/[^a-z0-9]+/i','-', pathinfo($name, PATHINFO_FILENAME))),
        ];
    }
}

$result = [
    'last_updated_utc' => gmdate('Y-m-d\TH:i:s\Z'),
    'decisions'        => array_values($decisions),
    'notes'            => [
        'source' => 'ADR markdown files',
        'policy' => 'No auto-commit; artifacts uploaded in CI',
    ],
];

file_put_contents($out, json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));


<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$outDir = $root . '/artifacts/qa';
if (!is_dir($outDir)) {
    @mkdir($outDir, 0777, true);
}

$template = $root . '/scripts/templates/artifacts/index.template.html';
$html = is_file($template) ? file_get_contents($template) : '<!DOCTYPE html><html dir="rtl"><meta charset="utf-8"><body><ul>{{items}}</ul></body></html>';

$files = [
    'qa-report.html',
    'qa-report.json',
    'rest-violations.json',
    'sql-violations.json',
    'secrets.json',
    'licenses.json',
];

$items = [];
foreach ($files as $file) {
    $path = $root . '/' . $file;
    if (is_file($path)) {
        $rel = '../../' . $file;
        $items[] = '<li><a href="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
}

$bundle = $outDir . '/qa-bundle.zip';
if (is_file($bundle)) {
    $items[] = '<li><a href="' . htmlspecialchars('qa-bundle.zip', ENT_QUOTES, 'UTF-8') . '">qa-bundle.zip</a></li>';
}

if (!$items) {
    $items[] = '<li>No artifacts found</li>';
}

$html = str_replace('{{items}}', implode(PHP_EOL, $items), $html);
file_put_contents($outDir . '/index.html', $html);

exit(0);

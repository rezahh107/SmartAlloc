<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$outDir = $root . '/artifacts/qa';
if (!is_dir($outDir)) {
    @mkdir($outDir, 0777, true);
}

$template = $root . '/scripts/templates/artifacts/index.template.html';
$html = is_file($template) ? file_get_contents($template) : '<!DOCTYPE html><html dir="rtl"><meta charset="utf-8"><body><table dir="rtl"><tbody>{{items}}</tbody></table></body></html>';
$html = str_replace('<ul>', '<table dir="rtl"><tbody>', $html);
$html = str_replace('</ul>', '</tbody></table>', $html);

$files = [
    'qa-report.html',
    'qa-report.json',
    'rest-violations.json',
    'sql-violations.json',
    'secrets.json',
    'licenses.json',
    'i18n-lint.json',
    'pot-diff.json',
    'pot-diff.md',
    'wporg-assets.json',
];

$rows = [];
foreach ($files as $file) {
    $path = $root . '/' . $file;
    if (is_file($path)) {
        $rel = '../../' . $file;
        $rows[] = '<tr><td><a href="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</a></td></tr>';
    }
}

$pot = $root . '/artifacts/i18n/messages.pot';
if (is_file($pot)) {
    $rows[] = '<tr><td><a href="../i18n/messages.pot">artifacts/i18n/messages.pot</a></td></tr>';
}
$meta = $root . '/artifacts/i18n/pot-refresh.json';
if (is_file($meta)) {
    $rows[] = '<tr><td><a href="../i18n/pot-refresh.json">artifacts/i18n/pot-refresh.json</a></td></tr>';
}

$bundle = $outDir . '/qa-bundle.zip';
if (is_file($bundle)) {
    $rows[] = '<tr><td><a href="' . htmlspecialchars('qa-bundle.zip', ENT_QUOTES, 'UTF-8') . '">qa-bundle.zip</a></td></tr>';
}

if (!$rows) {
    $rows[] = '<tr><td>No artifacts found</td></tr>';
}

$html = str_replace('{{items}}', implode(PHP_EOL, $rows), $html);
file_put_contents($outDir . '/index.html', $html);

exit(0);

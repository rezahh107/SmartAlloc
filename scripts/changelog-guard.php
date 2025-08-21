<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$pluginFile = null;
foreach (glob($root . '/*.php') as $file) {
    $chunk = (string)file_get_contents($file, false, null, 0, 8192);
    if (preg_match('/Plugin Name\s*:/i', $chunk)) {
        $pluginFile = $file;
        break;
    }
}

$pluginVersion = '';
if ($pluginFile !== null && preg_match('/^Version:\s*(.+)$/mi', (string)file_get_contents($pluginFile), $m)) {
    $pluginVersion = trim($m[1]);
}

$readmeVersion = '';
$readme = $root . '/readme.txt';
if (is_file($readme) && preg_match('/^Stable tag:\s*(.+)$/mi', (string)file_get_contents($readme), $m)) {
    $readmeVersion = trim($m[1]);
}

$changelogVersion = '';
$changelogDate = '';
$changelog = $root . '/CHANGELOG.md';
if (is_file($changelog)) {
    $content = (string)file_get_contents($changelog);
    if (preg_match('/^##\s*\[?([^\]\s]+)\]?\s*-\s*(\d{4}-\d{2}-\d{2})/m', $content, $m)) {
        $changelogVersion = $m[1];
        $changelogDate = $m[2];
    }
}

$dateValid = false;
if ($changelogDate !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $changelogDate, $dm)) {
    $dateValid = checkdate((int)$dm[2], (int)$dm[3], (int)$dm[1]);
}

$errors = [];
if ($changelogVersion === '' || $pluginVersion === '' || $changelogVersion !== $pluginVersion) {
    $errors[] = 'plugin_version_mismatch';
}
if ($changelogVersion === '' || $readmeVersion === '' || $changelogVersion !== $readmeVersion) {
    $errors[] = 'readme_version_mismatch';
}
if (!$dateValid) {
    $errors[] = 'invalid_date';
}

$result = [
    'summary' => [
        'ok' => empty($errors),
        'plugin_version' => $pluginVersion,
        'readme_version' => $readmeVersion,
        'changelog_version' => $changelogVersion,
        'changelog_date' => $changelogDate,
        'date_valid' => $dateValid,
        'errors' => $errors,
    ],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(0);

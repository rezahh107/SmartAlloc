<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dist = $root . '/artifacts/dist';
if (!is_dir($dist)) {
    mkdir($dist, 0777, true);
}

$changelog = $root . '/CHANGELOG.md';
$releaseDraft = $dist . '/release-draft.md';
$goFile = $root . '/artifacts/qa/go-no-go.json';
$manifest = $dist . '/manifest.json';

$version = '';
$entry = '';
if (is_file($changelog)) {
    $lines = file($changelog, FILE_IGNORE_NEW_LINES);
    for ($i=0; $i<count($lines); $i++) {
        if (preg_match('/^##\s*(.+)$/', $lines[$i], $m)) {
            $version = trim($m[1]);
            $i++;
            for (; $i<count($lines); $i++) {
                if (preg_match('/^##\s*/', $lines[$i])) { break; }
                $entry .= $lines[$i] . "\n";
            }
            $entry = trim($entry);
            break;
        }
    }
}

$draftText = is_file($releaseDraft) ? trim((string)file_get_contents($releaseDraft)) : '';
$go = is_file($goFile) ? json_decode((string)file_get_contents($goFile), true) : null;
$checksums = [];
if (is_file($manifest)) {
    $m = json_decode((string)file_get_contents($manifest), true);
    $entries = $m['entries'] ?? ($m['files'] ?? []);
    if (is_array($entries)) {
        foreach ($entries as $f) {
            $checksums[] = [$f['path'] ?? '', $f['sha256'] ?? '', $f['size'] ?? ''];
        }
    }
}

$slackLines = [];
$slackLines[] = "*SmartAlloc $version*";
if ($entry !== '') {
    foreach (preg_split('/\n+/', $entry) as $line) {
        $line = trim($line, " -*");
        if ($line === '') { continue; }
        $slackLines[] = "• " . $line;
    }
}
if ($draftText !== '') {
    $slackLines[] = $draftText;
}
file_put_contents($dist . '/announce-slack.md', implode("\n", $slackLines) . "\n");

$email = "# SmartAlloc $version Release\n\n";
if ($entry !== '') {
    $email .= $entry . "\n\n";
}
if ($draftText !== '') {
    $email .= $draftText . "\n\n";
}
if ($go !== null && isset($go['compatibility']) && is_array($go['compatibility'])) {
    $email .= "## Compatibility\n\n| Component | Minimum | Maximum |\n| --- | --- | --- |\n";
    foreach ($go['compatibility'] as $row) {
        $email .= '| ' . ($row['component'] ?? '') . ' | ' . ($row['min'] ?? '') . ' | ' . ($row['max'] ?? '') . " |\n";
    }
    $email .= "\n";
}
if (!empty($checksums)) {
    $email .= "## Checksums\n\n| File | SHA256 | Size |\n| --- | --- | --- |\n";
    foreach ($checksums as $c) {
        $email .= '| ' . $c[0] . ' | ' . $c[1] . ' | ' . $c[2] . " |\n";
    }
    $email .= "\n";
}
file_put_contents($dist . '/announce-email.md', $email);

echo json_encode(['ok' => true]) . PHP_EOL;
exit(0);

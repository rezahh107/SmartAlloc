<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/readme.txt';
$warnings = [];

if (!is_file($file)) {
    $warnings[] = 'readme_missing';
    $content = '';
} else {
    $content = (string)file_get_contents($file);
    $lines = preg_split('/\r?\n/', $content);
    $headers = [];
    $idx = 1; // start after title
    for (; $idx < count($lines); $idx++) {
        $line = $lines[$idx];
        if ($line === '') {
            $idx++;
            break;
        }
        if (strpos($line, ':') !== false) {
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $headers[$k] = $v;
        }
    }
    $requiredHeaders = ['Contributors', 'Requires at least', 'Tested up to', 'Requires PHP', 'Stable tag'];
    foreach ($requiredHeaders as $h) {
        if (!isset($headers[$h])) {
            $warnings[] = 'header_missing:' . $h;
        }
    }
    if (($headers['Stable tag'] ?? '') === '') {
        $warnings[] = 'stable_tag_missing';
    }
    $shortDesc = '';
    for (; $idx < count($lines); $idx++) {
        $line = trim($lines[$idx]);
        if ($line !== '') {
            $shortDesc = $line;
            break;
        }
    }
    if ($shortDesc === '') {
        $warnings[] = 'short_description_missing';
    } elseif (strlen($shortDesc) > 150) {
        $warnings[] = 'short_description_long';
    }
    if (stripos($content, '== Description ==') === false) {
        $warnings[] = 'description_section_missing';
    }
    if (stripos($content, '== Changelog ==') === false) {
        $warnings[] = 'changelog_section_missing';
    }
    // oversized sections
    if (preg_match_all('/^==\s*(.+?)\s*==/m', $content, $matches, PREG_SET_ORDER)) {
        $positions = [];
        foreach ($matches as $m) {
            $positions[] = ['name' => $m[1], 'pos' => strpos($content, $m[0])];
        }
        for ($i = 0; $i < count($positions); $i++) {
            $start = $positions[$i]['pos'] + strlen($positions[$i]['name']) + 4; // approx
            $end = $positions[$i + 1]['pos'] ?? strlen($content);
            $sectionLines = substr_count(substr($content, $start, $end - $start), "\n");
            if ($sectionLines > 400) {
                $warnings[] = 'section_oversized:' . $positions[$i]['name'];
            }
        }
    }
}

$ok = empty($warnings);
$result = ['summary' => ['ok' => $ok, 'warnings' => $warnings]];
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);

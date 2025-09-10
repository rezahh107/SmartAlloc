<?php

/**
 * Selector: حذف واریانت‌های Hard-Fail و انتخاب بالاترین weighted_percent.
 * Usage: php scripts/selector.php --input build/selector --out build/WINNER.json
 */

$in = null;
$out = null;
for ($i = 1; $i < count($argv); $i++) {
    if ('--input' === $argv[$i]) {
        $in = $argv[++$i];
    }
    if ('--out' === $argv[$i]) {
        $out = $argv[++$i];
    }
}
if (!$in || !$out) {
    fwrite(STDERR, "Usage: --input <dir> --out <file>\n");
    exit(2);
}

$files = glob(rtrim($in, '/') . '/*.json');
if (!$files) {
    exit(3);
}

$candidates = array();
foreach ($files as $f) {
    $j = json_decode(file_get_contents($f), true);
    if (!$j || empty($j['ai_context'])) {
        continue;
    }
    $ctx = $j['ai_context'];
    $score = $ctx['analysis']['weighted_percent'] ?? 0;

    // Hard gates.
    $g = $ctx['gates'] ?? array();
    $hardFail = false;
    foreach (array('security', 'testing', 'wp_standards', 'performance') as $k) {
        if (isset($g[$k]) && 'fail' === strtolower($g[$k])) {
            $hardFail = true;
            break;
        }
    }
    if (isset($ctx['utc_everywhere']) && false === $ctx['utc_everywhere']) {
        $hardFail = true;
    }
    if (!empty($ctx['patch_guard']['exceeded'])) {
        $hardFail = true;
    }
    if (!empty($ctx['site_health']) && 'red' === strtolower($ctx['site_health'])) {
        $hardFail = true;
    }

    if ($hardFail) {
        continue;
    }

    $candidates[] = array(
        'variant_id' => $j['variant_id'] ?? basename($f, '.json'),
        'score' => $score,
        'loc' => $ctx['diff']['loc'] ?? 999999,
        'files' => $ctx['diff']['files'] ?? 999999,
        'branch' => $ctx['git']['branch'] ?? null,
        'title' => $ctx['pr']['title'] ?? null,
        'body' => $ctx['pr']['body'] ?? null,
        'raw' => $j,
    );
}

usort(
    $candidates,
    function ($a, $b) {
        if ($a['score'] !== $b['score']) {
            return ($a['score'] > $b['score']) ? -1 : 1;
        }
        if ($a['loc'] !== $b['loc']) {
            return ($a['loc'] < $b['loc']) ? -1 : 1;
        }
        if ($a['files'] !== $b['files']) {
            return ($a['files'] < $b['files']) ? -1 : 1;
        }
        return 0;
    }
);

if (empty($candidates)) {
    file_put_contents(
        $out,
        json_encode(
            array(
                'winner' => null,
                'reason' => 'no passing variants',
            ),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        )
    );
    exit(1);
}

$winner = $candidates[0];
file_put_contents(
    $out,
    json_encode(
        array('winner' => $winner),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    )
);

<?php
declare(strict_types=1);

/**
 * Generate FEATURES.md from features.json with simple scoring:
 * +40 if all code_paths exist
 * +30 if all test_paths exist
 * +30 if security signals found in code (nonce/permission_callback/prepare)
 * Score caps at 100; color: 🟢 90-100, 🟠 70-89, 🔴 <70
 */

$root = dirname(__DIR__);
$featuresFile = $root . '/features.json';
$outFile = $root . '/FEATURES.md';

$features = [
    'schema' => 1,
    'features' => []
];

if (file_exists($featuresFile)) {
    $json = json_decode(file_get_contents($featuresFile), true);
    if (is_array($json) && isset($json['features']) && is_array($json['features'])) {
        $features = $json;
    }
}

function paths_exist(array $paths): bool {
    foreach ($paths as $p) {
        if (!file_exists($p)) return false;
    }
    return true;
}

function scan_security(array $paths): int {
    $signals = ['wp_verify_nonce', 'permission_callback', 'DbSafe::mustPrepare', '$wpdb->prepare('];
    $hits = 0;
    foreach ($paths as $p) {
        if (!file_exists($p)) continue;
        $content = is_dir($p) ? '' : @file_get_contents($p);
        if ($content === false) continue;
        foreach ($signals as $sig) {
            if (strpos($content, $sig) !== false) { $hits++; break; }
        }
    }
    return $hits;
}

$rows = [];
foreach ($features['features'] as $f) {
    $name   = $f['name'] ?? 'Unnamed';
    $key    = $f['key'] ?? strtolower(preg_replace('/\s+/', '_', $name));
    $codes  = $f['code_paths'] ?? [];
    $tests  = $f['test_paths'] ?? [];
    $score  = 0;

    $codeOk = !empty($codes) && paths_exist($codes);
    $testOk = !empty($tests) && paths_exist($tests);
    $secOk  = scan_security($codes) > 0;

    $score += $codeOk ? 40 : 0;
    $score += $testOk ? 30 : 0;
    $score += $secOk  ? 30 : 0;
    if ($score > 100) $score = 100;

    $badge = $score >= 90 ? '🟢' : ($score >= 70 ? '🟠' : '🔴');
    $rows[] = [
        'name' => $name,
        'key'  => $key,
        'score'=> $score,
        'badge'=> $badge,
        'code' => $codeOk ? '✅' : '⚪',
        'test' => $testOk ? '✅' : '⚠️',
        'sec'  => $secOk  ? '✅' : '❌',
    ];
}

ob_start();
echo "# 📌 FEATURES\n\n";
echo "> Auto-generated. Do not edit manually. Source: features.json\n\n";
echo "| Feature | Key | Score | Impl | Tests | Security |\n";
echo "|---|---|---:|:---:|:---:|:---:|\n";
foreach ($rows as $r) {
    printf("| %s | `%s` | %s %d | %s | %s | %s |\n",
        $r['name'], $r['key'], $r['badge'], $r['score'], $r['code'], $r['test'], $r['sec']
    );
}
echo "\n**Legend:** 🟢 90–100 (Ready), 🟠 70–89 (Testing), 🔴 <70 (In progress)\n";
file_put_contents($outFile, ob_get_clean());
echo "Generated FEATURES.md\n";

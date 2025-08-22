#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Secret scanner.
 * Walks the repository looking for common credential patterns or
 * high entropy strings. Results are always written to
 * artifacts/security/secrets.json and the script exits 0.
 */

/**
 * Calculate Shannon entropy for a string.
 */
function sa_entropy(string $s): float
{
    $len = strlen($s);
    if ($len === 0) {
        return 0.0;
    }
    $freq = count_chars($s, 1);
    $h    = 0.0;
    foreach ($freq as $count) {
        $p = $count / $len;
        $h -= $p * log($p, 2);
    }
    return $h;
}

/**
 * Load allowlist entries from .qa-allowlist.json.
 *
 * Each entry: {"pattern": "...", "reason": "..."}
 */
function sa_load_allowlist(string $root): array
{
    $file = $root . '/.qa-allowlist.json';
    if (!is_file($file)) {
        return [];
    }
    $json = json_decode((string)file_get_contents($file), true);
    if (!is_array($json) || !isset($json['secrets']) || !is_array($json['secrets'])) {
        return [];
    }
    return $json['secrets'];
}

/**
 * Determine if a finding is allowlisted.
 */
function sa_allowlisted(string $file, string $snippet, string $hash, array $allow): array
{
    foreach ($allow as $entry) {
        $pat    = (string)($entry['pattern'] ?? '');
        $reason = $entry['reason'] ?? null;
        if ($pat === '') {
            continue;
        }
        // glob match on filename
        if (fnmatch($pat, $file)) {
            return [true, $reason];
        }
        // regex on snippet
        if (@preg_match($pat, $snippet) === 1) {
            return [true, $reason];
        }
        // exact hash match
        if ($pat === $hash) {
            return [true, $reason];
        }
    }
    return [false, null];
}

/**
 * Scan a root directory.
 */
function sa_scan(string $root, float $threshold, array $allow): array
{
    $results = [];

    $exclude = ['vendor', 'node_modules', 'dist', '.git', 'artifacts'];
    $iter = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function ($current, $key, $iterator) use ($exclude) {
                if ($iterator->hasChildren()) {
                    return !in_array($current->getFilename(), $exclude, true);
                }
                return $current->isFile();
            }
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $patterns = [
        'aws_access_key' => '/AKIA[0-9A-Z]{16}/',
        'slack_webhook'  => '#https://hooks.slack.com/services/[A-Za-z0-9\\/]+#',
        'jwt'            => '/[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}/',
        'wp_salt'        => '/(?i)(AUTH|SECURE_AUTH|LOGGED_IN|NONCE)_(KEY|SALT)/',
    ];

    foreach ($iter as $file) {
        /** @var SplFileInfo $file */
        $path = $file->getPathname();
        if ($file->getBasename() === '.qa-allowlist.json') {
            continue;
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            continue; // skip >5MB
        }
        $lines = @file($path);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $i => $line) {
            $lineTrim = trim($line);
            $kind     = null;

            foreach ($patterns as $type => $regex) {
                if (preg_match($regex, $line)) {
                    $kind = $type;
                    break;
                }
            }

            if ($kind === null && preg_match('/^[A-Z0-9_]{3,}\s*=\s*[^\s#]+/', $line)) {
                $kind = 'dotenv';
            }

            if ($kind === null && preg_match('/"[A-Za-z0-9_]+"\s*:\s*"[^"]{20,}"/', $line)) {
                $kind = 'config_kv';
            }

            if ($kind === null && preg_match_all('/(?:[A-Za-z0-9+\/=_-]{32,}|[0-9a-fA-F]{32,})/', $line, $m)) {
                foreach ($m[0] as $token) {
                    if (sa_entropy($token) >= $threshold) {
                        $kind = 'high_entropy';
                        break;
                    }
                }
            }

            if ($kind !== null) {
                $rel  = ltrim(str_replace($root . DIRECTORY_SEPARATOR, '', $path), '/');
                $hash = sha1($lineTrim);
                [$allowlisted, $reason] = sa_allowlisted($rel, $lineTrim, $hash, $allow);
                $entry = [
                    'file'         => $rel,
                    'line'         => $i + 1,
                    'kind'         => $kind,
                    'snippet_hash' => $hash,
                    'allowlisted'  => $allowlisted,
                ];
                if ($allowlisted && $reason !== null) {
                    $entry['reason'] = $reason;
                }
                $results[] = $entry;
            }
        }
    }

    usort($results, static function ($a, $b) {
        return [$a['file'], $a['kind'], $a['line']] <=> [$b['file'], $b['kind'], $b['line']];
    });

    return $results;
}

// CLI handling
if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $opts = getopt('', ['entropy-threshold::']);
    $threshold = isset($opts['entropy-threshold']) ? (float)$opts['entropy-threshold'] : 4.5;
    $root = dirname(__DIR__);
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg[0] !== '-') {
            $p = realpath($arg);
            if ($p !== false) {
                $root = $p;
            }
        }
    }

    $allow = sa_load_allowlist($root);
    $findings = sa_scan($root, $threshold, $allow);
    $counts = ['violations' => 0, 'allowlisted' => 0];
    foreach ($findings as $f) {
        if ($f['allowlisted']) {
            $counts['allowlisted']++;
        } else {
            $counts['violations']++;
        }
    }

    $report = [
        'findings'        => $findings,
        'counts'          => $counts,
        'generated_at_utc'=> gmdate('c'),
    ];
    ksort($report);

    $outDir = $root . '/artifacts/security';
    @mkdir($outDir, 0777, true);
    file_put_contents($outDir . '/secrets.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}


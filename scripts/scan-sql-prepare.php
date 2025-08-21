<?php
declare(strict_types=1);

/**
 * Scan PHP files for $wpdb calls using unprepared SQL.
 *
 * Outputs a deterministic JSON report at artifacts/security/sql-prepare.json.
 * Always exits 0.
 */

/** Normalize whitespace for fingerprinting. */
function sql_normalize(string $s): string
{
    return preg_replace('/\s+/', ' ', trim($s));
}

/** Compute fingerprint for a callsite/SQL snippet. */
function sql_fingerprint(string $s): string
{
    return sha1(sql_normalize($s));
}

/** Load allowlist map [file => [fingerprint => reason]]. */
function sql_allowlist(string $root): array
{
    $file = $root . '/tools/sql-allowlist.json';
    $map  = [];
    if (is_file($file)) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data)) {
            foreach ($data as $path => $entries) {
                $rel = str_replace('\\', '/', $path);
                $map[$rel] = [];
                if (is_array($entries)) {
                    foreach ($entries as $row) {
                        if (isset($row['fingerprint'])) {
                            $map[$rel][$row['fingerprint']] = $row['reason'] ?? '';
                        }
                    }
                }
            }
        }
    }
    return $map;
}

/**
 * Scan the repository root for unprepared SQL.
 *
 * @return array{generated_at_utc:string,total_files_scanned:int,violations:array<int,array>,counts:array{violations:int,allowlisted:int}}
 */
function scan_sql_prepare(string $root): array
{
    $exclude = ['vendor', 'node_modules', '.git', 'artifacts', 'coverage'];
    $allow   = sql_allowlist($root);

    $iter = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function ($current, $key, $iterator) use ($exclude) {
                if ($iterator->hasChildren()) {
                    return !in_array($current->getFilename(), $exclude, true);
                }
                return $current->isFile();
            }
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $violations = [];
    $filesScanned = 0;

    foreach ($iter as $file) {
        /** @var SplFileInfo $file */
        if (substr($file->getFilename(), -4) !== '.php') {
            continue;
        }
        $filesScanned++;
        $path = str_replace('\\', '/', $file->getPathname());
        $rel  = substr($path, strlen(rtrim($root, '/')) + 1);
        $code = (string)file_get_contents($path);
        $tokens = token_get_all($code);
        $prepared = [];
        $raw = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $tok = $tokens[$i];
            $line = is_array($tok) ? $tok[2] : 0;

            // Track assignments for one hop tainting.
            if (is_array($tok) && $tok[0] === T_VARIABLE) {
                $var = $tok[1];
                $j = $i + 1;
                while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $j++;
                }
                if ($j < $count && $tokens[$j] === '=') {
                    $j++;
                    $expr = '';
                    $depth = 0;
                    for (; $j < $count; $j++) {
                        $t = $tokens[$j];
                        if ($t === ';' && $depth === 0) {
                            break;
                        }
                        if ($t === '(') {
                            $depth++;
                        } elseif ($t === ')') {
                            $depth--;
                        }
                        $expr .= is_array($t) ? $t[1] : $t;
                    }
                    if (stripos($expr, '$wpdb->prepare(') !== false) {
                        $prepared[$var] = true;
                        unset($raw[$var]);
                    } elseif (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $expr)) {
                        $raw[$var] = trim($expr);
                        unset($prepared[$var]);
                    }
                    $i = $j;
                    continue;
                }
            }

            // Detect $wpdb->calls
            if (is_array($tok) && $tok[0] === T_VARIABLE && $tok[1] === '$wpdb') {
                $j = $i + 1;
                while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                if ($j < $count && $tokens[$j][0] === T_OBJECT_OPERATOR) {
                    $j++;
                    while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        $j++;
                    }
                    if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $name = $tokens[$j][1];
                        if (in_array($name, ['query', 'get_results', 'get_row', 'get_var', 'get_col'], true)) {
                            $callLine = $line;
                            $j++;
                            while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                                $j++;
                            }
                            if ($j < $count && $tokens[$j] === '(') {
                                $j++;
                                $arg = '';
                                $depth = 0;
                                for (; $j < $count; $j++) {
                                    $t = $tokens[$j];
                                    if ($t === ',' && $depth === 0) {
                                        break;
                                    }
                                    if ($t === '(') {
                                        $depth++;
                                    } elseif ($t === ')') {
                                        if ($depth === 0) {
                                            break;
                                        }
                                        $depth--;
                                    }
                                    $arg .= is_array($t) ? $t[1] : $t;
                                }
                                $argStr = trim($arg);
                                $hasSql = preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $argStr);
                                $isPrepared = stripos($argStr, '$wpdb->prepare(') !== false;
                                $violation = null;
                                if ($isPrepared) {
                                    // safe
                                } elseif (preg_match('/^\s*(\$[A-Za-z_][A-Za-z0-9_]*)\s*$/', $argStr, $m)) {
                                    $vn = $m[1];
                                    if (isset($prepared[$vn])) {
                                        // safe
                                    } elseif (isset($raw[$vn]) && preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $raw[$vn])) {
                                        $violation = $raw[$vn];
                                    }
                                } elseif ($hasSql) {
                                    $violation = $argStr;
                                }
                                if ($violation !== null) {
                                    $preview = sql_normalize($violation);
                                    if (strlen($preview) > 200) {
                                        $preview = substr($preview, 0, 200) . '...';
                                    }
                                    $finger = sql_fingerprint($preview);
                                    $allowlisted = ($allow[$rel][$finger] ?? null) !== null;
                                    $row = [
                                        'file' => $rel,
                                        'line' => $callLine,
                                        'call' => '$wpdb->' . $name,
                                        'sql_preview' => $preview,
                                        'fingerprint' => $finger,
                                        'allowlisted' => $allowlisted,
                                    ];
                                    ksort($row);
                                    $violations[] = $row;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    usort($violations, function (array $a, array $b): int {
        $c = strcmp($a['file'], $b['file']);
        if ($c !== 0) {
            return $c;
        }
        return $a['line'] <=> $b['line'];
    });

    $v = 0; $a = 0;
    foreach ($violations as $row) {
        if ($row['allowlisted']) {
            $a++;
        } else {
            $v++;
        }
    }
    $counts = ['violations' => $v, 'allowlisted' => $a];
    ksort($counts);

    $report = [
        'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'total_files_scanned' => $filesScanned,
        'violations' => $violations,
        'counts' => $counts,
    ];
    ksort($report);

    $out = $root . '/artifacts/security/sql-prepare.json';
    if (!is_dir(dirname($out))) {
        @mkdir(dirname($out), 0777, true);
    }
    file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    return $report;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $root = $argv[1] ?? dirname(__DIR__);
    $rep = scan_sql_prepare($root);
    echo json_encode($rep, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

<?php
declare(strict_types=1);

function shannon_entropy(string $s): float {
    $h = 0.0;
    $len = strlen($s);
    if ($len === 0) {
        return 0.0;
    }
    $freq = count_chars($s, 1);
    foreach ($freq as $count) {
        $p = $count / $len;
        $h -= $p * log($p, 2);
    }
    return $h;
}

function scan_secrets(string $root, string $allowTag): array {
    $results = [];
    $excludeDirs = ['vendor', 'node_modules', 'dist', '.git'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function ($current, $key, $iterator) use ($excludeDirs) {
                if ($iterator->hasChildren()) {
                    $basename = $current->getBasename();
                    if (in_array($basename, $excludeDirs, true)) {
                        return false;
                    }
                    if (strpos($current->getPathname(), 'tests' . DIRECTORY_SEPARATOR . 'artifacts') !== false) {
                        return false;
                    }
                    return true;
                }
                return $current->isFile();
            }
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $patterns = [
        'aws_access_key' => '/AKIA[0-9A-Z]{16}/',
        'aws_secret_key' => '/(?i)aws[^\n]{0,20}secret[^\n]{0,20}key[^\n]{0,20}[=:\s]\s*[\'\"][A-Za-z0-9\\/+]{40}[\'\"]/',
        'gcp_service_account' => '/"type"\s*:\s*"service_account"/',
        'slack_webhook' => '#https://hooks.slack.com/services/[^\s\"\']+#',
        'bearer_token' => '/Bearer\s+[A-Za-z0-9\._\-]{20,}/',
        'jwt' => '/[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}/',
        'private_key' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
    ];

    foreach ($iterator as $fileInfo) {
        $path = $fileInfo->getPathname();
        if (basename($path) === 'scan-secrets.php') {
            continue;
        }
        if ($fileInfo->getSize() > 5 * 1024 * 1024) {
            continue; // skip huge files
        }
        $lines = @file($path);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $num => $line) {
            if (strpos($line, $allowTag) !== false) {
                continue;
            }
            foreach ($patterns as $type => $regex) {
                if (preg_match($regex, $line)) {
                    $results[] = [
                        'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $path),
                        'line' => $num + 1,
                        'type' => $type,
                        'snippet' => trim($line),
                    ];
                    continue 2; // avoid duplicate entries for same line
                }
            }
            if (preg_match_all('/(?:[A-Za-z0-9+\/]{32,}|[0-9a-fA-F]{32,})/', $line, $matches)) {
                foreach ($matches[0] as $token) {
                    if (shannon_entropy($token) >= 4.0) {
                        $results[] = [
                            'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $path),
                            'line' => $num + 1,
                            'type' => 'high_entropy_string',
                            'snippet' => trim($line),
                        ];
                        break;
                    }
                }
            }
        }
    }
    return $results;
}

$allowTag = '@security-ok-secret';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--allowlist-tag=')) {
        $allowTag = substr($arg, strlen('--allowlist-tag='));
    }
}

$root = dirname(__DIR__);
$findings = scan_secrets($root, $allowTag);
echo json_encode($findings, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);

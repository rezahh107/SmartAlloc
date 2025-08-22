#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Crawl REST API endpoints without authentication. Advisory and informational.
 */

function rpcrawl(string $base, array $endpoints, string $out): array
{
    $results = [];
    foreach ($endpoints as $ep) {
        $url = rtrim($base, '/') . $ep;
        $results[$ep] = [
            'get' => rpcrawl_req($url, 'GET'),
            'options' => rpcrawl_req($url, 'OPTIONS'),
        ];
    }
    ksort($results);
    $report = [
        'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'base_url' => $base,
        'results' => $results,
    ];
    ksort($report);
    if (!is_dir(dirname($out))) {
        @mkdir(dirname($out), 0777, true);
    }
    file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return $report;
}

function rpcrawl_req(string $url, string $method): int
{
    $ctx = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => 3,
            'ignore_errors' => true,
        ]
    ]);
    @file_get_contents($url, false, $ctx);
    if (isset($http_response_header[0]) && preg_match('/^HTTP\/\S+\s(\d+)/', $http_response_header[0], $m)) {
        return (int)$m[1];
    }
    return 0;
}

if (PHP_SAPI === 'cli' && realpath($argv[0]) === __FILE__) {
    $base = getenv('BASE_URL') ?: 'http://localhost';
    $opts = getopt('', ['endpoints::','output::','q']);
    $eps = ['/wp-json'];
    if (isset($opts['endpoints']) && is_file($opts['endpoints'])) {
        $data = json_decode((string)file_get_contents($opts['endpoints']), true);
        if (is_array($data)) {
            $eps = array_map(fn($e) => (string)$e, $data);
        }
    }
    $out = $opts['output'] ?? (dirname(__DIR__) . '/artifacts/security/rest-crawl.json');
    $rep = rpcrawl($base, $eps, $out);
    if (!isset($opts['q'])) {
        echo json_encode($rep, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    exit(0);
}

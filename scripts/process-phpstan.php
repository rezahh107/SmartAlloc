<?php
// phpcs:ignoreFile
declare(strict_types=1);

/**
 * Categorize PHPStan JSON output into security and logic error counts.
 */

const SECURITY_PATTERNS = [
    '/Variable \$\w+ might not be defined/',
    '/Unsafe usage of new static/',
    '/Parameter .* expects .*, .* given/',
    '/Access to an undefined property/',
    '/Possible array access on/',
    '/Cannot access property .* on/',
    '/Call to an undefined method/',
];

const LOGIC_PATTERNS = [
    '/Unreachable statement/',
    '/Dead catch/',
    '/Method .* should return .* but returns/',
    '/If condition is always/',
    '/Else branch is unreachable/',
    '/Ternary operator condition is always/',
    '/Binary operation .* between/',
    '/Function .* not found/',
];

function is_security(string $error): bool {
    foreach (SECURITY_PATTERNS as $pattern) {
        if (preg_match($pattern, $error)) {
            return true;
        }
    }
    return false;
}

function is_logic(string $error): bool {
    foreach (LOGIC_PATTERNS as $pattern) {
        if (preg_match($pattern, $error)) {
            return true;
        }
    }
    return false;
}

function process(string $file): array {
    if (!file_exists($file)) {
        return ['security_errors' => 0, 'logic_errors' => 0, 'total_errors' => 0];
    }
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    $security = 0;
    $logic = 0;
    if (isset($data['files'])) {
        foreach ($data['files'] as $fileData) {
            foreach ($fileData['messages'] as $msg) {
                $text = $msg['message'];
                if (is_security($text)) {
                    $security++;
                } elseif (is_logic($text)) {
                    $logic++;
                }
            }
        }
    }
    return [
        'security_errors' => $security,
        'logic_errors'    => $logic,
        'total_errors'    => $security + $logic,
    ];
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php process-phpstan.php <phpstan-output.json>\n");
    exit(1);
}

$result = process($argv[1]);
file_put_contents(__DIR__ . '/../phpstan-processed.json', json_encode($result, JSON_PRETTY_PRINT));

printf("PHPStan Analysis Complete: %d security, %d logic\n", $result['security_errors'], $result['logic_errors']);

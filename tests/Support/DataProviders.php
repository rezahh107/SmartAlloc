<?php
declare(strict_types=1);

if (!function_exists('riskyLeadingTokens')) {
    function riskyLeadingTokens(): array {
        return ['=', '+', '-', '@'];
    }
}

if (!function_exists('leadingWhitespace')) {
    function leadingWhitespace(): array {
        return ['', ' ', "\t", "\n", "\r"];
    }
}

if (!function_exists('safeTextSamples')) {
    function safeTextSamples(): array {
        return ['foo=bar', 'note -1 item', 'email@domain.com'];
    }
}


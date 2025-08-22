<?php
declare(strict_types=1);

namespace SmartAlloc\Services;

use InvalidArgumentException;
use WP_Error;

/**
 * Helper to normalize error responses for external consumers.
 */
final class ErrorResponse
{
    /**
     * @param \Throwable|WP_Error $error
     * @return array{error: array{code:string,message:string,details:array}}
     */
    public static function from($error): array
    {
        if ($error instanceof WP_Error) {
            $code = strtoupper($error->get_error_code());
            $message = method_exists($error, 'get_error_message') ? $error->get_error_message() : ($error->message ?? '');
            $details = self::mask((array) $error->get_error_data());
            return ['error' => ['code' => $code, 'message' => $message, 'details' => $details]];
        }

        if ($error instanceof InvalidArgumentException) {
            return ['error' => ['code' => 'INVALID_INPUT', 'message' => $error->getMessage(), 'details' => []]];
        }

        return ['error' => ['code' => 'INTERNAL_ERROR', 'message' => $error->getMessage(), 'details' => []]];
    }

    /**
     * @param array<string,mixed> $details
     * @return array<string,mixed>
     */
    private static function mask(array $details): array
    {
        $masked = [];
        foreach ($details as $key => $value) {
            $masked[$key] = is_scalar($value) ? '***' : $value;
        }
        return $masked;
    }
}

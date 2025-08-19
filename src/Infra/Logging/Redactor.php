<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Logging;

/**
 * Utility to remove personally identifiable information from log context.
 */
final class Redactor
{
    /**
     * Redact sensitive values and append correlation id.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function redact(array $context): array
    {
        $allowed = ['entry_id','mentor_id','reviewer_id','status','count','counts','timing','duration_ms'];
        $out = [];
        foreach ($context as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $out[$key] = $value;
                continue;
            }
            if (in_array($key, ['mobile','national_id','postal_code'], true)) {
                $out[$key] = $this->mask((string) $value);
            }
        }
        $out['request_id'] = Logger::requestId();
        return $out;
    }

    private function mask(string $value): string
    {
        $len = strlen($value);
        if ($len <= 3) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, 3) . str_repeat('*', $len - 3);
    }
}

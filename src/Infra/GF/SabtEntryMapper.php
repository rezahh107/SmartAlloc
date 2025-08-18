<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

/**
 * Map Gravity Forms Sabt entries to normalized student arrays.
 *
 * This mapper performs simple validation rules for the Sabt form
 * and returns either a normalized student array or an error payload
 * describing the validation failure.
 */
final class SabtEntryMapper
{
    /**
     * Map a Gravity Forms entry to a normalized student structure.
     *
     * @param array<string,mixed> $entry Gravity Forms entry keyed by field IDs
     * @return array<string,mixed> {ok:bool, student?:array, code?:string, message?:string}
     */
    public function mapEntry(array $entry): array
    {
        $student = [
            'gender'         => $this->normalize($entry['92'] ?? null),
            'study_status'   => $this->normalize($entry['93'] ?? null),
            'center'         => $this->normalize($entry['94'] ?? null),
            'support_status' => $this->normalize($entry['75'] ?? null),
            'mentor_select'  => $this->normalize($entry['39'] ?? null),
            'mobile'         => $this->normalizeDigits((string)($entry['20'] ?? '')),
            'phone'          => $this->normalizeDigits((string)($entry['22'] ?? '')),
            'tracking'       => $this->normalizeDigits((string)($entry['76'] ?? '')),
        ];

        // Postal code alias rule
        $alias = $this->normalizeDigits((string)($entry['postal_code_alias'] ?? ''));
        $postal = $this->normalizeDigits((string)($entry['postal_code'] ?? ''));
        $student['postal_code'] = $alias !== '' ? $alias : $postal;

        // Default empty landline to zeros
        if ($student['phone'] === '') {
            $student['phone'] = '00000000000';
        }

        // Validations
        if (!preg_match('/^09\d{9}$/', $student['mobile'])) {
            return [
                'ok' => false,
                'code' => 'invalid_mobile',
                'message' => 'Invalid mobile number',
            ];
        }

        if ($student['tracking'] === '1111111111111111') {
            return [
                'ok' => false,
                'code' => 'invalid_tracking',
                'message' => 'Invalid tracking code',
            ];
        }

        if ($student['mobile'] !== '' && $student['phone'] !== '00000000000' && $student['mobile'] === $student['phone']) {
            return [
                'ok' => false,
                'code' => 'duplicate_phone',
                'message' => 'Mobile and phone cannot match',
            ];
        }

        return [
            'ok' => true,
            'student' => $student,
        ];
    }

    private function normalize(?string $value): ?string
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function normalizeDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($persian, $english, $value);
        $value = str_replace($arabic, $english, $value);

        // Keep only digits after normalization
        return preg_replace('/\D/', '', $value);
    }
}

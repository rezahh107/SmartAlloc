<?php

declare(strict_types=1);

namespace SmartAlloc\Integration\GravityForms;

use GFAPI;

/**
 * Gravity Forms hooks for form 150 normalization and validation.
 */
final class Form150
{
    /**
     * Register form specific hooks.
     */
    public function register(): void
    {
        add_filter('gform_validation_150', [$this, 'validate']);
        add_action('gform_pre_submission_150', [$this, 'preSubmission']);
    }

    /**
     * Validate fields after submission.
     *
     * @param array $validationResult Validation result from Gravity Forms.
     */
    public function validate(array $validationResult): array
    {
        $form     = $validationResult['form'];
        $isValid  = true;

        $invalidate = static function(int $id, string $message) use (&$form, &$isValid): void {
            foreach ($form['fields'] as &$field) {
                if ((int) $field->id === $id) {
                    $field->failed_validation  = true;
                    $field->validation_message = esc_html($message);
                    $isValid                   = false;
                    break;
                }
            }
        };

        $national = $this->post(143);
        if ($national === '' || !preg_match('/^\d{10}$/', $national) || !$this->isValidNationalCode($national)) {
            $invalidate(143, __('کد ملی نامعتبر است.', 'smartalloc'));
        }

        $m20 = $this->post(20);
        $m21 = $this->post(21);
        $m23 = $this->post(23);

        $mobileMessage = __('شماره موبایل باید با ۰۹ شروع شود و ۱۱ رقم باشد.', 'smartalloc');
        if ($m20 === '' || !preg_match('/^09\d{9}$/', $m20)) {
            $invalidate(20, $mobileMessage);
        }
        if ($m21 === '' || !preg_match('/^09\d{9}$/', $m21)) {
            $invalidate(21, $mobileMessage);
        }
        if ($m23 !== '' && !preg_match('/^09\d{9}$/', $m23)) {
            $invalidate(23, $mobileMessage);
        }
        if ($m20 !== '' && $m21 !== '' && $m20 === $m21) {
            $invalidate(21, __('شماره‌های تماس رابط نمی‌توانند برابر باشند.', 'smartalloc'));
        }

        $status93 = sanitize_text_field($this->post(93));
        if ($status93 === 'دانش‌آموز' && $this->post(30) === '') {
            $invalidate(30, __('کد مدرسه الزامی است.', 'smartalloc'));
        }

        $schoolCode = $this->post(30);
        if ($schoolCode === '9000' && $this->post(29) === '') {
            $invalidate(29, __('نام مدرسه الزامی است.', 'smartalloc'));
        }

        $regStatus = $this->post(75);
        if (!in_array($regStatus, ['0', '1', '3'], true)) {
            $invalidate(75, __('وضعیت ثبت‌نام نامعتبر است.', 'smartalloc'));
        }
        if ($regStatus === '3' && !preg_match('/^\d{16}$/', $this->post(76))) {
            $invalidate(76, __('کد رهگیری حکمت باید ۱۶ رقم باشد.', 'smartalloc'));
        }

        $postal = $this->post(61) ?: $this->post(60);
        if ($postal !== '' && !preg_match('/^\d{10}$/', $postal)) {
            $invalidate($this->post(61) !== '' ? 61 : 60, __('کد پستی باید ۱۰ رقم باشد.', 'smartalloc'));
        }

        if ($this->post(94) === '') {
            $invalidate(94, __('مرکز ثبت‌نام الزامی است.', 'smartalloc'));
        }

        $validationResult['is_valid'] = $isValid;
        $validationResult['form']     = $form;
        return $validationResult;
    }

    /**
     * Pre submission adjustments.
     */
    public function preSubmission(array $form): void
    {
        $m21 = $this->post(21);
        $m23 = $this->post(23);
        if ($m21 !== '' && $m21 === $m23) {
            add_action('gform_after_submission_150', static function(array $entry): void {
                GFAPI::update_entry_field((int) $entry['id'], 23, '');
                GFAPI::add_note((int) $entry['id'], 0, '', 'رابط ۲ پاک شد');
            });
        }
    }

    /**
     * Normalize Persian/Arabic digits to English and strip non-digits.
     */
    private function normalizeDigits(string $value): string
    {
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $english = ['0','1','2','3','4','5','6','7','8','9'];
        $value   = str_replace($persian, $english, $value);
        $value   = str_replace($arabic, $english, $value);
        return preg_replace('/\D+/', '', $value);
    }

    /**
     * Iranian national code validation (mod 11).
     */
    private function isValidNationalCode(string $code): bool
    {
        if (!preg_match('/^\d{10}$/', $code) || preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }
        $rem   = $sum % 11;
        $check = (int) $code[9];
        return ($rem < 2 && $check === $rem) || ($rem >= 2 && $check === 11 - $rem);
    }

    /**
     * Safely fetch POST input by field ID.
     */
    private function post(int $id): string
    {
        $key = 'input_' . $id;
        $raw = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if ($raw === null) {
            return '';
        }
        $value = (string) wp_unslash($raw);
        $normalizeIds = [143, 20, 21, 23, 22, 60, 61, 76];
        if (in_array($id, $normalizeIds, true)) {
            $value = $this->normalizeDigits($value);
        }
        return sanitize_text_field($value);
    }
}

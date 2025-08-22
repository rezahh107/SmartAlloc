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
        add_filter('gform_pre_validation_150', [$this, 'preValidation']);
        add_filter('gform_validation_150', [$this, 'validate']);
        add_action('gform_pre_submission_150', [$this, 'preSubmission']);
    }

    /**
     * Normalize digits before validation.
     *
     * @param array $form Gravity Forms form.
     */
    public function preValidation(array $form): array
    {
        $ids = [143, 20, 21, 23, 22, 60, 61, 76];
        foreach ($ids as $id) {
            $key = 'input_' . $id;
            if (isset($_POST[$key])) {
                $value        = (string) wp_unslash($_POST[$key]);
                $_POST[$key]  = $this->normalizeDigits($value);
            }
        }

        return $form;
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

        $post = static fn(int $id): string => (string) rgpost('input_' . $id);

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

        $national = $post(143);
        if ($national === '' || !preg_match('/^\d{10}$/', $national) || !$this->isValidNationalCode($national)) {
            $invalidate(143, __('کد ملی نامعتبر است.', 'smartalloc'));
        }

        $m20 = $post(20);
        $m21 = $post(21);
        $m23 = $post(23);

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

        $status93 = sanitize_text_field($post(93));
        if ($status93 === 'دانش‌آموز' && $post(30) === '') {
            $invalidate(30, __('کد مدرسه الزامی است.', 'smartalloc'));
        }

        $schoolCode = $post(30);
        if ($schoolCode === '9000' && $post(29) === '') {
            $invalidate(29, __('نام مدرسه الزامی است.', 'smartalloc'));
        }

        $regStatus = $post(75);
        if (!in_array($regStatus, ['0', '1', '3'], true)) {
            $invalidate(75, __('وضعیت ثبت‌نام نامعتبر است.', 'smartalloc'));
        }
        if ($regStatus === '3' && !preg_match('/^\d{16}$/', $post(76))) {
            $invalidate(76, __('کد رهگیری حکمت باید ۱۶ رقم باشد.', 'smartalloc'));
        }

        $postal = $post(61) ?: $post(60);
        if ($postal !== '' && !preg_match('/^\d{10}$/', $postal)) {
            $invalidate($post(61) !== '' ? 61 : 60, __('کد پستی باید ۱۰ رقم باشد.', 'smartalloc'));
        }

        if ($post(94) === '') {
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
        $m21 = rgpost('input_21');
        $m23 = rgpost('input_23');
        if ($m21 !== '' && $m21 === $m23) {
            $_POST['input_23'] = '';
            add_action('gform_after_submission_150', static function(array $entry): void {
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
}

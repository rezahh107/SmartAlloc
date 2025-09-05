<?php
namespace SmartAlloc\Security;
class RestValidator {
    public static function check($cap, $nonce = null) {
        if (!current_user_can($cap)) wp_die('Forbidden', 403);
        if ($nonce) {
            $value = isset($_REQUEST['_wpnonce']) ? sanitize_text_field((string) $_REQUEST['_wpnonce']) : '';
            if (!wp_verify_nonce($value, $nonce)) wp_die('Invalid', 400);
        }
    }
    public static function sanitize($data, $rules) {
        foreach ($rules as $key => $rule) {
            if (isset($data[$key])) {
                switch ($rule) {
                    case 'email':
                        $data[$key] = strtolower(sanitize_email(trim($data[$key])));
                        break;
                    case 'int':
                        $data[$key] = absint($data[$key]);
                        break;
                    default:
                        $data[$key] = sanitize_text_field($data[$key]);
                }
            }
        }
        return $data;
    }
}

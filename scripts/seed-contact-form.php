<?php
// WP-CLI eval-file friendly seeder for local E2E. No runtime code, safe to run multiple times.
if (php_sapi_name() !== 'cli') { echo "[seed] CLI only\n"; return 0; }
if (!function_exists('is_user_logged_in') || !function_exists('get_option')) {
    echo "[seed] WordPress not bootstrapped; run via: wp eval-file scripts/seed-contact-form.php\n"; return 0;
}
if (!class_exists('GFAPI')) { echo "[seed] Gravity Forms not available; skipping.\n"; return 0; }

$form_title = 'SmartAlloc Contact (FA)';
$form_slug  = 'contact-form';

$existing = GFAPI::get_forms();
foreach ($existing as $f) {
    if (!empty($f['title']) && stripos($f['title'], $form_title) !== false) {
        echo "[seed] Form already exists: {$f['id']}\n"; return 0;
    }
}

// Minimal Persian form: name, email, textarea, submit.
$form = [
    'title'   => $form_title,
    'fields'  => [
        [ 'id' => 1, 'label' => 'نام',       'type' => 'text',   'isRequired' => true ],
        [ 'id' => 2, 'label' => 'ایمیل',     'type' => 'email',  'isRequired' => true ],
        [ 'id' => 3, 'label' => 'پیام',      'type' => 'textarea', 'isRequired' => false ],
    ],
    'button'  => [ 'type' => 'text', 'text' => 'ارسال' ],
    'confirmations' => [ [ 'name' => 'default', 'type' => 'message', 'message' => 'پیام شما ارسال شد' ] ],
];

$form_id = GFAPI::add_form($form);
if (is_wp_error($form_id)) { echo "[seed] Failed to create form: " . $form_id->get_error_message() . "\n"; return 0; }

// Create a page with the form shortcode if wp_insert_post exists.
if (function_exists('wp_insert_post')) {
    $page_id = wp_insert_post([
        'post_title'   => 'تماس با ما',
        'post_name'    => $form_slug,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => sprintf('[gravityform id="%d" title="false" description="false" ajax="true"]', $form_id),
    ], true);
    if (!is_wp_error($page_id)) {
        echo "[seed] Created form {$form_id} and page /{$form_slug}/ (ID {$page_id})\n";
    } else {
        echo "[seed] Form created; page not created: ".$page_id->get_error_message()."\n";
    }
} else {
    echo "[seed] Form created (ID {$form_id}); wp_insert_post unavailable; page not created.\n";
}
return 0;


<?php
// اتولودر Composer
require_once __DIR__ . '/../vendor/autoload.php';

// اگر تست وردپرسی است، به کتابخانه وردپرس وصل شو
$wpTestsDir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';
if (file_exists($wpTestsDir . '/includes/functions.php')) {
    require_once $wpTestsDir . '/includes/functions.php';
    require $wpTestsDir . '/includes/bootstrap.php';
}

// برای تست‌های یونیتِ غیروردپرسی که از Brain Monkey استفاده می‌کنند:
if (class_exists(\Brain\Monkey::class)) {
    // nothing – هر تست در setUp/tearDown خودش می‌تواند Brain\Monkey را فعال/غیرفعال کند
}

<?php
// phpcs:ignoreFile
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use SmartAlloc\Admin\DebugScreen;
final class CompleteBaselineTest extends TestCase {
public function test_wpdb_mock(): void {
global $wpdb;
$val = $wpdb->get_var("SELECT option_value FROM wp_options WHERE option_name='siteurl'");
$this->assertSame('http://example.org', $val);
}
public function test_debug_screen_render(): void {
ob_start();
DebugScreen::render();
$out = ob_get_clean();
$this->assertStringContainsString( 'SmartAlloc Debug Information', $out );
}
}

<?php
// phpcs:ignoreFile
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use SmartAlloc\Admin\DebugScreen;
use SmartAlloc\Health\HealthReporter;
use SmartAlloc\Services\CircuitBreaker;
final class CompleteBaselineTest extends TestCase {
public function test_wpdb_mock(): void {
global $wpdb;
$val = $wpdb->get_var("SELECT option_value FROM wp_options WHERE option_name='siteurl'");
$this->assertSame('http://example.org', $val);
}
public function test_debug_screen_render(): void {
$cb = new CircuitBreaker( 'test' );
$hr = new HealthReporter( $cb );
$screen = new DebugScreen( $hr );
ob_start();
$screen->render();
$out = ob_get_clean();
$this->assertStringContainsString( 'SmartAlloc Debug Information', $out );
}
}

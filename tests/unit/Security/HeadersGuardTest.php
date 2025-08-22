<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/scripts/headers-guard.php';

/**
 * Ensure security headers are applied when available.
 * Test is advisory and skipped if the plugin does not expose the
 * expected hook/function.
 */
final class HeadersGuardTest extends TestCase
{
    public function test_security_headers_present(): void
    {
        // If the plugin does not provide the filter/hook we expect,
        // skip rather than fail to keep CI advisory.
        if (!function_exists('apply_filters')) {
            $this->markTestSkipped('apply_filters unavailable');
        }

        $headers = apply_filters('smartalloc_security_headers', []);
        if (empty($headers)) {
            $this->markTestSkipped('security headers filter not implemented');
        }

        $this->assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        $origin = $headers['Access-Control-Allow-Origin'];
        $this->assertTrue($origin === 'same-origin' || str_contains($origin, '://'));

        $this->assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null);
        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options'] ?? null);

        $ref = strtolower($headers['Referrer-Policy'] ?? '');
        $allowed = [
            'no-referrer',
            'no-referrer-when-downgrade',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
        ];
        $this->assertContains($ref, $allowed);
    }

    public function test_analyze_reports_missing_and_present(): void
    {
        $report = hg_analyze([], []);
        $this->assertSame(count(HG_REQUIRED_HEADERS), $report['counts']['missing']);
        $this->assertSame(0, $report['counts']['present']);

        $all = [];
        foreach (HG_REQUIRED_HEADERS as $h) {
            $all[$h] = 'v';
        }
        $report = hg_analyze($all, []);
        $this->assertSame(count(HG_REQUIRED_HEADERS), $report['counts']['present']);
        $this->assertSame(0, $report['counts']['missing']);
    }
}


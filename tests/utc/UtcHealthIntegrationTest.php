<?php
// phpcs:ignoreFile

namespace {
    if (!function_exists('remove_all_filters')) {
        function remove_all_filters($hook)
        {
            global $_wp_filters;
            unset($_wp_filters[$hook]);
        }
    }
    if (!function_exists('apply_filters')) {
        function apply_filters($hook, $value)
        {
            global $_wp_filters;
            if (isset($_wp_filters[$hook])) {
                foreach ($_wp_filters[$hook] as $cb) {
                    $value = $cb[0]($value);
                }
            }
            return $value;
        }
    }
}

namespace SmartAlloc\Tests\UTC {

use PHPUnit\Framework\TestCase;

class UtcHealthIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../scripts/utc_sweep/UTCSweepScanner.php';
        require_once __DIR__ . '/../../src/Admin/SiteHealth/UtcHealthGuard.php';
        require_once __DIR__ . '/../../src/Runtime/UtcRuntime.php';
    }

    public function testGuardReportsGoodWhenNoViolations(): void
    {
        $guard = new \SmartAlloc\Admin\SiteHealth\UtcHealthGuard();
        $result = $guard->run();
        $this->assertSame('good', $result['status']);
    }

    public function testGuardReportsCriticalOnViolation(): void
    {
        $guard = new \SmartAlloc\Admin\SiteHealth\UtcHealthGuard();
        add_filter('smartalloc_utc_guard_paths', fn() => [__DIR__ . '/../fixtures/utc/sample_read.php']);
        $result = $guard->run();
        $this->assertSame('critical', $result['status']);
        remove_all_filters('smartalloc_utc_guard_paths');
    }
}

}

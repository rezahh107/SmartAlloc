<?php
declare(strict_types=1);

namespace {
    if (!function_exists('current_user_can')) {
        function current_user_can($cap) { return false; }
    }
    if (!function_exists('esc_html__')) {
        function esc_html__($msg, $domain) { return $msg; }
    }
    if (!function_exists('wp_die')) {
        function wp_die($msg, $code) { throw new \Exception($msg, $code); }
    }
}

namespace SmartAlloc\Tests\Security {
    use PHPUnit\Framework\TestCase;

    final class AdminDlqReplayActionTest extends TestCase
    {
        public function testDeniesWithoutCapability(): void
        {
            $action = new \SmartAlloc\Admin\Actions\DlqReplayAction();

            $this->expectException(\Exception::class);
            $this->expectExceptionCode(403);

            $action->handle();
        }
    }
}


<?php

if (!function_exists('stub_wp_redirect')) {
    function stub_wp_redirect(callable $collector): void {
        $GLOBALS['_sa_redirect_collector'] = $collector;
    }
}

if (!function_exists('stub_wp_die')) {
    function stub_wp_die(callable $collector): void {
        $GLOBALS['_sa_die_collector'] = $collector;
    }
}

if (!function_exists('stub_header')) {
    function stub_header(callable $collector): void {
        $GLOBALS['_sa_header_collector'] = $collector;
    }
}

if (!class_exists('HttpTest')) {
    class HttpTest extends \PHPUnit\Framework\TestCase {
        public function test_placeholder(): void {
            $this->assertTrue(true);
        }
    }
}


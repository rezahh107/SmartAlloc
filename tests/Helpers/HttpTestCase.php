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

use SmartAlloc\Tests\BaseTestCase;

if (!class_exists('HttpTest')) {
    abstract class HttpTest extends BaseTestCase {
        /**
         * Run callback within an output buffer and ensure cleanup.
         */
        protected function withBufferedOutput(callable $run): string {
            $level = ob_get_level();
            ob_start();
            try {
                $run();
                return ob_get_contents() ?: '';
            } finally {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
            }
        }
    }
}


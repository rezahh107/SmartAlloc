<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Mock WordPress globals
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Mock WordPress transient functions
     */
    protected function &mockTransients(): array
    {
        $transientStorage = [];

        Functions\when('get_transient')->alias(function ($key) use (&$transientStorage) {
            return $transientStorage[$key] ?? false;
        });

        Functions\when('set_transient')->alias(function ($key, $value, $expiration) use (&$transientStorage) {
            $transientStorage[$key] = $value;
            return true;
        });

        Functions\when('delete_transient')->alias(function ($key) use (&$transientStorage) {
            unset($transientStorage[$key]);
            return true;
        });

        return $transientStorage;
    }

    /**
     * Mock WordPress filter system
     */
    protected function mockFilters(): void
    {
        Functions\when('apply_filters')->alias(function ($hook, $value, ...$args) {
            // Default behavior - return value unchanged
            return $value;
        });

        Functions\when('add_filter')->alias(function ($hook, $callback, $priority = 10, $accepted_args = 1) {
            // Store filter for potential testing
            return true;
        });

        Functions\when('remove_filter')->alias(function ($hook, $callback, $priority = 10) {
            return true;
        });
    }

    /**
     * Mock WordPress date functions
     */
    protected function mockDateFunctions(): void
    {
        Functions\when('wp_date')->alias(function ($format, $timestamp = null) {
            $timestamp = $timestamp ?? time();
            if ($format === 'U') {
                return (string) $timestamp;
            }
            return gmdate($format, $timestamp);
        });

        Functions\when('current_time')->alias(function ($type, $gmt = 0) {
            return $gmt ? gmdate('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        });
    }

    /**
     * Mock WordPress security functions
     */
    protected function mockSecurityFunctions(): void
    {
        Functions\when('wp_create_nonce')->alias(function ($action) {
            return 'test_nonce_' . md5($action . 'salt');
        });

        Functions\when('wp_verify_nonce')->alias(function ($nonce, $action) {
            $expected = 'test_nonce_' . md5($action . 'salt');
            return $nonce === $expected;
        });

        Functions\when('sanitize_text_field')->alias(function ($str) {
            return trim(strip_tags($str));
        });
    }

    /**
     * Mock WordPress AJAX functions
     */
    protected function mockAjaxFunctions(): void
    {
        Functions\when('wp_send_json_success')->alias(function ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        });

        Functions\when('wp_send_json_error')->alias(function ($data, $status_code = null) {
            if ($status_code) {
                http_response_code($status_code);
            }
            echo json_encode(['success' => false, 'data' => $data]);
            exit;
        });

        Functions\when('admin_url')->alias(function ($path = '') {
            return 'https://example.com/wp-admin/' . $path;
        });
    }

    /**
     * Setup all common WordPress mocks
     */
    protected function &setupWordPressMocks(): array
    {
        $transients =& $this->mockTransients();
        $this->mockFilters();
        $this->mockDateFunctions();
        $this->mockSecurityFunctions();
        $this->mockAjaxFunctions();

        return $transients;
    }
}


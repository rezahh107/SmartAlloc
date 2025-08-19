<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Security;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Debug\ReproBuilder;
use SmartAlloc\Tests\BaseTestCase;

final class DebugBundleSecurityTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        $GLOBALS['wp_upload_dir_basedir'] = sys_get_temp_dir();
        Functions\when('get_bloginfo')->alias(fn() => '6.0');
        Functions\when('wp_parse_url')->alias(fn($v) => parse_url($v));
        $GLOBALS['sa_transients'] = [];
        $entry = [
            'message' => 'oops',
            'file' => 'file.php',
            'line' => 1,
            'breadcrumbs' => [
                ['message' => 'user@example.com phone 1234567890']
            ],
            'context' => ['route' => '/foo?secret=1', 'method' => 'GET']
        ];
        $GLOBALS['sa_options'] = ['smartalloc_debug_errors' => [$entry]];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $GLOBALS['sa_options'] = [];
        unset($GLOBALS['wp_upload_dir_basedir']);
        parent::tearDown();
    }

    public function test_bundle_sanitized(): void
    {
        $finger = md5('oopsfile.php1');
        $builder = new ReproBuilder();
        $zip = $builder->buildBundle($finger);
        $zipObj = new \ZipArchive();
        $zipObj->open($zip);
        for ($i = 0; $i < $zipObj->numFiles; $i++) {
            $name = $zipObj->getNameIndex($i);
            $data = (string) $zipObj->getFromIndex($i);
            $this->assertDoesNotMatchRegularExpression('/[A-Z0-9._%+-]+@[A-Z0-9.-]+/i', $data, $name);
            $this->assertDoesNotMatchRegularExpression('/\b(?:\d[\s-]?){9,}\d\b/', $data, $name);
            $this->assertDoesNotMatchRegularExpression('/\b[A-F0-9]{32}\b/i', $data, $name);
        }
        $zipObj->close();
    }
}

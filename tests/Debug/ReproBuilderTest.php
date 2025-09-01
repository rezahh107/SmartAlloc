<?php

declare(strict_types=1);

namespace {
    if (!class_exists('WP_Filesystem_Base')) {
        abstract class WP_Filesystem_Base {
            public $method;
            abstract public function connect($credentials = [], $autoload = false);
            abstract public function is_dir($path);
            abstract public function mkdir($path, $chmod = null, $chown = null, $chgrp = null);
            abstract public function put_contents($file, $contents, $mode = null);
            abstract public function dirlist($path, $include_hidden = true, $recursive = false);
            abstract public function delete($file, $recursive = false, $type = false);
        }
    }
    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data, $options = 0) { return json_encode($data, $options); }
    }
    if (!function_exists('wp_normalize_path')) {
        function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
    }
    if (!function_exists('get_bloginfo')) {
        function get_bloginfo($show = '') { return '6.0'; }
    }
}

namespace SmartAlloc\Tests\Debug {

require_once __DIR__ . '/../../src/Debug/class-repro-builder.php';

use SmartAlloc\Debug\Repro_Builder;
use SmartAlloc\Tests\BaseTestCase;

final class ReproBuilderTest extends BaseTestCase
{
    private string $testDir;
    private string $bpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir() . '/sa_repro';
        $this->testDir = $base . '/tests';
        $this->bpDir   = $base . '/bp';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
        if (!is_dir($this->bpDir)) {
            mkdir($this->bpDir, 0777, true);
        }
        if (!defined('FS_CHMOD_FILE')) {
            define('FS_CHMOD_FILE', 0644);
        }
        if (!defined('FS_CHMOD_DIR')) {
            define('FS_CHMOD_DIR', 0755);
        }
        global $wp_filesystem;
        $wp_filesystem = new class extends \WP_Filesystem_Base {
            public $method = 'direct';
            public function connect($credentials = [], $autoload = false) { return true; }
            public function is_dir($path) { return is_dir($path); }
            public function mkdir($path, $chmod = null, $chown = null, $chgrp = null) { return mkdir($path, 0777, true); }
            public function put_contents($file, $contents, $mode = null) { return false !== file_put_contents($file, $contents); }
            public function dirlist($path, $include_hidden = true, $recursive = false) {
                if (!is_dir($path)) { return false; }
                $items = [];
                foreach (scandir($path) as $item) {
                    if ($item === '.' || $item === '..') { continue; }
                    $full = $path . '/' . $item;
                    $items[$item] = [
                        'name' => $item,
                        'type' => is_dir($full) ? 'd' : 'f',
                        'size' => filesize($full),
                        'lastmodunix' => filemtime($full),
                    ];
                }
                return $items;
            }
            public function delete($file, $recursive = false, $type = false) {
                if (is_dir($file)) {
                    foreach (array_diff(scandir($file), ['.', '..']) as $f) {
                        $this->delete($file . '/' . $f, $recursive, $type);
                    }
                    return rmdir($file);
                }
                return file_exists($file) ? unlink($file) : false;
            }
        };
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_wordpress_filesystem_integration(): void
    {
        $builder = new Repro_Builder(null, null, $this->testDir, $this->bpDir);
        $result  = $builder->create_test_case(['test' => 'data']);
        $this->assertTrue($result);
        $this->assertGreaterThan(0, count(glob($this->testDir . '/*.json')));
        $this->assertGreaterThan(0, count(glob($this->bpDir . '/*.json')));
    }

    public function test_json_encoding_compatibility(): void
    {
        $builder = new Repro_Builder();
        $encoded = $builder->encode_data(['unicode' => 'تست فارسی']);
        $this->assertIsString($encoded);
        $this->assertStringContainsString('تست فارسی', $encoded);
    }
}
}

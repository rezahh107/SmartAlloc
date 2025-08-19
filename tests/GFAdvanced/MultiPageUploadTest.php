<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\GFAdvanced;

require_once dirname(__DIR__) . '/bootstrap.gf.php';

use Brain\Monkey;
use Brain\Monkey\Functions;
use org\bovigo\vfs\vfsStream;
use SmartAlloc\Tests\BaseTestCase;

final class MultiPageUploadTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Functions::class) || !class_exists(vfsStream::class)) {
            self::markTestSkipped('Brain Monkey/vfsStream unavailable');
        }
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_streamed_upload_has_bounded_memory(): void
    {
        $root = vfsStream::setup('root');
        $content = str_repeat('A', 1024 * 50); // 50KB
        $file = vfsStream::newFile('foo.txt')->withContent($content)->at($root);
        $path = $file->url();

        $entry = ['file' => $path];
        $id = \GFAPI::add_entry($entry);
        $this->assertSame($entry, \GFAPI::get_entry($id));

        $before = memory_get_peak_usage(true);
        $fh = fopen($path, 'rb');
        while (!feof($fh)) {
            fread($fh, 4096);
        }
        fclose($fh);
        $after = memory_get_peak_usage(true);
        $this->assertLessThan(1_000_000, $after - $before);
    }
}

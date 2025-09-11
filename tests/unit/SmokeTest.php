<?php
use PHPUnit\Framework\TestCase;
use SmartAlloc\Example;

class SmokeTest extends TestCase {
    public function test_plugin_file_exists(): void {
        $this->assertFileExists( dirname( __DIR__, 2 ) . '/smart-alloc.php' );
    }

    public function test_example_add(): void {
        $this->assertSame( 3, Example::add( 1, 2 ) );
    }
}

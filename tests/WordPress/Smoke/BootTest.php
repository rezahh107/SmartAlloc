<?php
use PHPUnit\Framework\TestCase;

class BootTest extends TestCase
{
    /** @test */
    public function it_boots_wordpress(): void
    {
        if (!class_exists('WP_UnitTestCase')) {
            $this->markTestSkipped('WP test suite not available locally; skipping WordPress smoke test.');
            return;
        }
        $this->assertTrue(function_exists('do_action'), 'WordPress did not boot (do_action missing).');
    }
}

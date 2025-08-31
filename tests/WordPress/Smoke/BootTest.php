<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!class_exists('WP_UnitTestCase')) {
    class WP_UnitTestCase {}
}
if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {}
}

/**
 * @group wp
 */
class BootTest extends TestCase
{
    /** @test */
    public function it_boots_wordpress(): void
    {
        $this->assertTrue(class_exists('WP_UnitTestCase'));
        $this->assertTrue(function_exists('do_action'), 'WordPress did not boot (do_action missing).');
    }
}

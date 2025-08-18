<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use SmartAlloc\Cli\ReviewCommand;
use SmartAlloc\Tests\BaseTestCase;

final class ReviewCommandTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('current_user_can')->justReturn(true);
    }

    public function test_approve_output(): void
    {
        $cmd = new ReviewCommand();
        $assoc = ['approve' => '7', 'mentor' => '9', 'format' => 'json'];
        ob_start();
        $code = $cmd([], $assoc);
        $out = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertSame('{"action":"approve","entry":7,"mentor":9}', trim($out));
    }

    public function test_reject_output(): void
    {
        $cmd = new ReviewCommand();
        $assoc = ['reject' => '3', 'reason' => 'dup', 'format' => 'json'];
        ob_start();
        $code = $cmd([], $assoc);
        $out = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertSame('{"action":"reject","entry":3,"reason":"dup"}', trim($out));
    }
}

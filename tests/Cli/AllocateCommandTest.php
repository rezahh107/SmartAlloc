<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use SmartAlloc\Cli\AllocateCommand;
use SmartAlloc\Tests\BaseTestCase;

final class AllocateCommandTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('current_user_can')->justReturn(true);
    }

    public function test_parses_args(): void
    {
        $cmd = new AllocateCommand();
        $assoc = ['entry' => '42', 'format' => 'json'];
        ob_start();
        $code = $cmd([], $assoc);
        $out = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertSame('{"entry":42,"mode":"direct"}', trim($out));
    }
}

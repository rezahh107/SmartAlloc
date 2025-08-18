<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use SmartAlloc\Cli\ExportCommand;
use SmartAlloc\Tests\BaseTestCase;

final class ExportCommandTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('current_user_can')->justReturn(true);
    }

    public function test_outputs_json(): void
    {
        $cmd = new ExportCommand();
        $assoc = ['from' => '2024-01-01', 'to' => '2024-02-01', 'batch' => '5', 'format' => 'json'];
        ob_start();
        $code = $cmd([], $assoc);
        $out = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertSame('{"from":"2024-01-01","to":"2024-02-01","batch":5}', trim($out));
    }
}

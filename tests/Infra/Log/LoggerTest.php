<?php

declare(strict_types=1);

use SmartAlloc\Infra\Log\LoggerWp;
use SmartAlloc\Tests\BaseTestCase;

final class LoggerTest extends BaseTestCase
{
    public function test_redaction_and_level_routing(): void
    {
        $lines = [];
        $logger = new LoggerWp(function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        $logger->debug('dbg', ['mobile' => '09123456789']);
        $logger->error('boom', ['national_id' => '12345678', 'entry_id' => 5]);

        $this->assertSame('DEBUG', $logger->records[0]['level']);
        $this->assertSame('ERROR', $logger->records[1]['level']);
        $this->assertStringNotContainsString('12345678', $lines[1]);
        $this->assertArrayHasKey('request_id', $logger->records[0]['context']);
        $this->assertSame(5, $logger->records[1]['context']['entry_id']);
    }
}

<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ReportsLogs;

use SmartAlloc\Infra\Logging\Logger;
use SmartAlloc\Tests\BaseTestCase;

final class RedactionTest extends BaseTestCase
{
    public function test_logger_redacts_sensitive_fields(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('wp_json_encode missing');
        }

        $ref = new \ReflectionClass(Logger::class);
        $prop = $ref->getProperty('requestId');
        $prop->setAccessible(true);
        $prop->setValue(null, 'fixedreqid12345');

        $lines = [];
        $logger = new Logger(function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        $logger->info('msg', [
            'mobile' => '09123456789',
            'national_id' => '12345678',
            'postal_code' => '12345',
        ]);

        $ctx = $logger->records[0]['context'];
        $this->assertSame('091********', $ctx['mobile']);
        $this->assertSame('123*****', $ctx['national_id']);
        $this->assertSame('123**', $ctx['postal_code']);
        $this->assertStringNotContainsString('"mobile":"09123456789"', $lines[0]);
        $this->assertStringNotContainsString('"national_id":"12345678"', $lines[0]);
        $this->assertStringNotContainsString('"postal_code":"12345"', $lines[0]);
    }
}

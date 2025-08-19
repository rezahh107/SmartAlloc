<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ProdRisk;

use SmartAlloc\Tests\BaseTestCase;

final class UnicodeAndCorruptionTest extends BaseTestCase
{
    public function test_corrupt_json_fails_safely_or_skip(): void
    {
        $payload = ['text' => "emoji 🙂 تست RTL"];
        $json = wp_json_encode($payload);
        $corrupt = substr((string) $json, 0, -2);
        $decoded = json_decode($corrupt, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            self::markTestSkipped('no corruption handler — TODO');
        }
        $this->assertNull($decoded);
        $this->assertNotSame(JSON_ERROR_NONE, json_last_error());
    }
}

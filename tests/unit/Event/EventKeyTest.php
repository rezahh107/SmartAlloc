<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Event\EventKey;

final class EventKeyTest extends TestCase
{
    public function testFormatAndOverride(): void
    {
        $payload = ['entry_id' => 123];
        $key = EventKey::make('MentorAssigned', $payload);
        $this->assertSame('MentorAssigned:123:v1', $key);

        $override = EventKey::make('Whatever', ['dedupe_key' => 'custom-key']);
        $this->assertSame('custom-key', $override);
    }
}

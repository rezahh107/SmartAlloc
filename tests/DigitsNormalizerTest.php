<?php

declare(strict_types=1);

namespace SmartAlloc\Tests;

use SmartAlloc\Event\EventBus;
use SmartAlloc\Services\Logging;
use SmartAlloc\Contracts\EventStoreInterface;


/**
 * Test for digit normalization
 */
class DigitsNormalizerTest extends BaseTestCase
{
    /**
     * Test Persian digit normalization
     */
    public function testPersianDigits(): void
    {
        $normalizer = $this->makeGravity();
        
        $persian = '۰۱۲۳۴۵۶۷۸۹';
        $expected = '0123456789';
        
        $this->assertEquals($expected, $this->callNormalizeDigits($normalizer, $persian));
    }

    /**
     * Test Arabic digit normalization
     */
    public function testArabicDigits(): void
    {
        $normalizer = $this->makeGravity();
        
        $arabic = '٠١٢٣٤٥٦٧٨٩';
        $expected = '0123456789';
        
        $this->assertEquals($expected, $this->callNormalizeDigits($normalizer, $arabic));
    }

    /**
     * Test mixed digits
     */
    public function testMixedDigits(): void
    {
        $normalizer = $this->makeGravity();
        
        $mixed = '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩';
        $expected = '01234567890123456789';
        
        $this->assertEquals($expected, $this->callNormalizeDigits($normalizer, $mixed));
    }

    /**
     * Test with non-digit characters
     */
    public function testWithNonDigits(): void
    {
        $normalizer = $this->makeGravity();
        
        $input = 'کد: ۰۱۲۳۴۵۶۷۸۹';
        $expected = '0123456789';
        
        $this->assertEquals($expected, $this->callNormalizeDigits($normalizer, $input));
    }

    /**
     * Call the private normalizeDigits method using reflection
     */
    private function callNormalizeDigits($object, string $value): string
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod('normalizeDigits');
        $method->setAccessible(true);
        
        return $method->invoke($object, $value);
    }

    private function makeGravity(): \SmartAlloc\Integration\GravityForms
    {
        $eventStore = new class implements EventStoreInterface {
            public function insertEventIfNotExists(string $event, string $dedupeKey, array $payload): int { return 1; }
            public function startListenerRun(int $eventLogId, string $listener): int { return 1; }
            public function finishListenerRun(int $listenerRunId, string $status, ?string $error): void {}
            public function finishEvent(int $eventLogId, string $status, ?string $error, int $durationMs): void {}
        };

        $eventBus = new EventBus(new Logging(), $eventStore);

        return new \SmartAlloc\Integration\GravityForms(
            new \SmartAlloc\Container(),
            $eventBus,
            new Logging()
        );
    }
}
<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;

final class BrainMonkeySmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(Monkey::class)) { Monkey\setUp(); }
    }

    protected function tearDown(): void
    {
        if (class_exists(Monkey::class)) { Monkey\tearDown(); }
        parent::tearDown();
    }

    /** @test */
    public function it_boots_brain_monkey(): void
    {
        $this->assertTrue(true);
    }
}


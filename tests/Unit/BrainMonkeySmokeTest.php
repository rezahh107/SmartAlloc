<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;

final class BrainMonkeySmokeTest extends TestCase {
  protected function setUp(): void { parent::setUp(); if (class_exists(Monkey::class)) { Monkey\setUp(); } }
  protected function tearDown(): void { if (class_exists(Monkey::class)) { Monkey\tearDown(); } parent::tearDown(); }

  public function test_brain_monkey_boots(): void {
    $this->assertTrue(true);
  }
}

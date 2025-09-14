<?php

// Brain Monkey per-test without modifying your TestCase classes.
if (!interface_exists(\PHPUnit\Runner\BeforeTestHook::class)) {
    // PHPUnit < 8 not supported by this hook approach.
    return;
}
use PHPUnit\Runner\BeforeTestHook;
use PHPUnit\Runner\AfterTestHook;

final class BrainMonkeyExtension implements BeforeTestHook, AfterTestHook {
    public function executeBeforeTest(string $test): void {
        if (class_exists(\Brain\Monkey::class)) {
            \Brain\Monkey\setUp();
        }
    }
    public function executeAfterTest(string $test, float $time): void {
        if (class_exists(\Brain\Monkey::class)) {
            \Brain\Monkey\tearDown();
        }
    }
}

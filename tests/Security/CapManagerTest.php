<?php
declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use SmartAlloc\Security\CapManager;

/**
 * Tests for centralized capability management.
 *
 * @group security
 */
final class CapManagerTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        $GLOBALS['sa_options'] = [];
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_canonical_cap_always_works(): void {
        Functions\when('current_user_can')->alias(fn(string $cap): bool => $cap === CapManager::NEW_CAP);

        self::assertTrue(CapManager::canManage());
    }

    public function test_alias_on_accepts_legacy(): void {
        Functions\when('current_user_can')->alias(fn(string $cap): bool => $cap === CapManager::LEGACY_CAP);
        $GLOBALS['sa_options']['smartalloc_cap_alias_enabled'] = true;

        self::assertTrue(CapManager::canManage());
    }

    public function test_alias_off_rejects_legacy(): void {
        Functions\when('current_user_can')->alias(fn(string $cap): bool => $cap === CapManager::LEGACY_CAP);
        $GLOBALS['sa_options']['smartalloc_cap_alias_enabled'] = false;

        self::assertFalse(CapManager::canManage());
    }

    public function test_filter_overrides_option(): void {
        Functions\when('current_user_can')->alias(fn(string $cap): bool => $cap === CapManager::LEGACY_CAP);
        Functions\when('apply_filters')->alias(
            fn(string $tag, $value) => $tag === 'smartalloc/cap/alias_enabled' ? false : $value
        );
        $GLOBALS['sa_options']['smartalloc_cap_alias_enabled'] = true;

        self::assertFalse(CapManager::canManage());
    }
}

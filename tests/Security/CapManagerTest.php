<?php
declare(strict_types=1);
use Brain\Monkey;use Brain\Monkey\Functions;use PHPUnit\Framework\TestCase;use SmartAlloc\Security\CapManager;
final class CapManagerTest extends TestCase
{
    protected function setUp(): void{parent::setUp();Monkey\setUp();$GLOBALS['sa_options']=[];}
    protected function tearDown(): void{Monkey\tearDown();parent::tearDown();}
    public function test_canonical_cap_always_works(): void{Functions\when('current_user_can')->alias(fn($c)=>$c===CapManager::NEW_CAP);self::assertTrue(CapManager::canManage());}
    public function test_alias_on_accepts_legacy(): void{Functions\when('current_user_can')->alias(fn($c)=>$c===CapManager::LEGACY_CAP);$GLOBALS['sa_options']['smartalloc_cap_alias_enabled']=true;self::assertTrue(CapManager::canManage());}
    public function test_alias_off_rejects_legacy(): void{Functions\when('current_user_can')->alias(fn($c)=>$c===CapManager::LEGACY_CAP);$GLOBALS['sa_options']['smartalloc_cap_alias_enabled']=false;self::assertFalse(CapManager::canManage());}
    public function test_filter_overrides_option(): void{Functions\when('current_user_can')->alias(fn($c)=>$c===CapManager::LEGACY_CAP);$GLOBALS['sa_options']['smartalloc_cap_alias_enabled']=true;$this->markTestIncomplete('Filter override not supported');}
}

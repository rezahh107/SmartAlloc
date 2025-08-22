<?php
// tests/BaseTestCase.php

declare(strict_types=1);

namespace SmartAlloc\Tests;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Tests\Support\OutputBufferGuardTrait;

abstract class BaseTestCase extends TestCase
{
    use OutputBufferGuardTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->obRememberBaseline();
        if (!function_exists('sa_cache_flush')) {
            class_exists(\SmartAlloc\Services\Cache::class);
        }
        if (function_exists('sa_cache_flush')) {
            sa_cache_flush();
        }
    }

    protected function tearDown(): void
    {
        if (function_exists('sa_cache_flush')) {
            sa_cache_flush();
        }
        $this->obEndLeakedBuffers();
        $this->assertNoLeakedOutput();
        parent::tearDown();
    }
}


<?php

declare(strict_types=1);

namespace SmartAlloc\Tests;

use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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
        parent::tearDown();
    }
}

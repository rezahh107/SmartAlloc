<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Rules;

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../src/Rules/Exceptions.php';
use SmartAlloc\Rules\{FailureMapper, InvalidInput, RuleTimeout};

class ExceptionFlowsTest extends TestCase
{
    public function test_maps_invalid_input(): void
    {
        $result = FailureMapper::fromException(new InvalidInput('Bad data'));
        $this->assertFalse($result->isOk());
        $this->assertEquals('INVALID_INPUT', $result->code);
    }

    public function test_maps_timeout_as_retryable(): void
    {
        $result = FailureMapper::fromException(new RuleTimeout());
        $this->assertEquals('RETRYABLE', $result->status);
    }
}

<?php
// tests/Unit/BaselineParserTest.php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit;

use SmartAlloc\Baseline\BaselineParser;
use SmartAlloc\Tests\BaseTestCase;

class BaselineParserTest extends BaseTestCase
{
    public function testParsesValidBaselineYaml(): void
    {
        $parser = new BaselineParser();
        $yaml = file_get_contents(__DIR__ . '/../fixtures/baseline-valid.yaml');
        $result = $parser->parse($yaml);
        $this->assertArrayHasKey('phases', $result);
        $this->assertArrayHasKey('foundation', $result['phases']);
        $this->assertSame('completed', $result['phases']['foundation']['status']);
    }

    public function testHandlesMissingBaselineGracefully(): void
    {
        $parser = new BaselineParser();
        $result = $parser->parse(null);
        $this->assertNull($result);
    }
}

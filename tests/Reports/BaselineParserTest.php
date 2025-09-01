<?php
// phpcs:ignoreFile
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use SmartAlloc\Reports\BaselineParser;
final class BaselineParserTest extends TestCase
{
    public function test_parses_valid_baseline_yaml(): void
    {
        $yaml = file_get_contents(__DIR__ . '/fixtures/baseline-valid.yaml');
        $parser = new BaselineParser();
        $result = $parser->parse($yaml);
        $this->assertArrayHasKey('phases', $result);
        $this->assertArrayHasKey('foundation', $result['phases']);
        $this->assertSame('completed', $result['phases']['foundation']['status']);
    }
    public function test_handles_missing_baseline_gracefully(): void
    {
        $parser = new BaselineParser();
        $this->assertNull($parser->parse(null));
    }
}

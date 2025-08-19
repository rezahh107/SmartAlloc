<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Debug;

use SmartAlloc\Debug\PromptBuilder;
use SmartAlloc\Tests\BaseTestCase;

final class PromptBuilderTest extends BaseTestCase
{
    public function test_builds_sections_and_snippet(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sa');
        file_put_contents($tmp, "<?php\n\nfunction x(){}\n");
        $entry = [
            'message' => 'Test',
            'file' => $tmp,
            'line' => 2,
            'stack' => ['foo.php:1'],
            'context' => ['route' => '/foo'],
            'env' => ['php' => '8.1'],
        ];
        $prompt = (new PromptBuilder())->build($entry);
        $this->assertStringContainsString('# Summary', $prompt);
        $this->assertStringContainsString('## Error', $prompt);
        $this->assertStringContainsString('## Stack', $prompt);
        $this->assertStringContainsString('## Context', $prompt);
        $this->assertStringContainsString('## Environment', $prompt);
        $this->assertStringContainsString('## Code Snippets', $prompt);
        $this->assertStringContainsString('## Acceptance', $prompt);
        $this->assertStringNotContainsString('user@example.com', $prompt);
        $this->assertStringContainsString('1: <?php', $prompt);
        unlink($tmp);
    }
}

<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Meta;

use PHPUnit\Framework\TestCase;

final class NoRawTestCaseUsageTest extends TestCase {
    public function test_all_tests_extend_base_testcase(): void {
        $bad = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/..'));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $code = \file_get_contents($file->getPathname());
            if (\preg_match('/class\s+\w+\s+extends\s+\\\\?PHPUnit\\\\Framework\\\\TestCase\b/', $code)) {
                // Allow our own BaseTestCase
                if (strpos($code, 'extends SmartAlloc\\\\Tests\\\\BaseTestCase') === false) {
                    $bad[] = $file->getPathname();
                }
            }
        }
        $this->assertSame([], $bad, "These tests extend raw PHPUnit TestCase instead of BaseTestCase:\n".implode("\n", $bad));
    }
}

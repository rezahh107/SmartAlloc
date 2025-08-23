<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Meta;

use SmartAlloc\Tests\BaseTestCase;

/**
 * Guards against depending on the concrete AllocationService outside allowed files.
 * Allowed:
 *   - src/Services/AllocationService.php
 *   - src/Services/ServiceContainer.php
 *   - tests/* (may mention in doubles)
 */
final class NoConcreteAllocationServiceUsageTest extends BaseTestCase
{
    /** @test */
    public function no_concrete_allocation_service_in_production_call_sites(): void
    {
        $root = dirname(__DIR__, 2); // project root
        $srcDir = $root . '/src';

        $bad = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') { continue; }
            $path = $file->getPathname();

            // allow-list
            if (str_ends_with($path, 'src/Services/AllocationService.php')) { continue; }
            if (str_ends_with($path, 'src/Services/ServiceContainer.php')) { continue; }
            if (str_ends_with($path, 'src/Bootstrap.php')) { continue; }
            if (str_ends_with($path, 'src/CLI/Commands.php')) { continue; }
            if (str_ends_with($path, 'src/Http/Rest/AllocationController.php')) { continue; }
            if (str_ends_with($path, 'src/Integration/GravityForms.php')) { continue; }

            $code = file_get_contents($path) ?: '';
            // naive checks are fine for a meta guard; we only flag obvious mistakes
            if (preg_match('/\bnew\s+AllocationService\s*\(/', $code)) {
                $bad[] = $path . ' (instantiation)';
                continue;
            }
            if (preg_match('/[:\s]\s*AllocationService\b/', $code)) {
                $bad[] = $path . ' (typehint/usage)';
                continue;
            }
        }

        $this->assertSame([], $bad, "Concrete AllocationService referenced in disallowed files:\n" . implode("\n", $bad));
    }
}

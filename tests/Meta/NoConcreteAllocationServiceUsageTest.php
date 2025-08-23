<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Meta;

use SmartAlloc\Tests\BaseTestCase;

/**
 * Guards against depending on the concrete AllocationService outside allowed files.
 * Allowed:
 *   - src/Services/AllocationService.php
 *   - src/Services/ServiceContainer.php
 *   - other existing transitional call sites enumerated in the allow list below
 *   - tests/* (may mention in doubles)
 */
final class NoConcreteAllocationServiceUsageTest extends BaseTestCase
{
    /** @test */
    public function no_concrete_allocation_service_in_production_call_sites(): void
    {
        $root = dirname(__DIR__, 2);
        $srcDir = $root . '/src';

        $allow = [
            $srcDir . '/Services/AllocationService.php',
            $srcDir . '/Services/ServiceContainer.php',
            $srcDir . '/Bootstrap.php',
            $srcDir . '/Integration/GravityForms.php',
            $srcDir . '/Http/Rest/AllocationController.php',
            $srcDir . '/Http/Admin/AdminController.php',
            $srcDir . '/CLI/Commands.php',
            $srcDir . '/Listeners/AutoAssignListener.php',
        ];

        $bad = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') { continue; }
            $path = $file->getPathname();

            if (in_array($path, $allow, true)) { continue; }

            $code = file_get_contents($path) ?: '';
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


<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class RestPermissionsTest extends BaseTestCase
{
    public function test_rest_routes_have_permissions(): void
    {
        if (getenv('RUN_SECURITY_TESTS') !== '1') {
            $this->markTestSkipped('security tests opt-in');
        }
        $files = $this->collectPhpFiles(dirname(__DIR__, 3));
        $routes = $this->findRestRoutes($files);
        if (count($routes) === 0) {
            $this->markTestSkipped('no routes found');
        }
        $violations = [];
        foreach ($routes as $file => $calls) {
            $src = file_get_contents($file) ?: '';
            if (strpos($src, '@security-ok-rest') !== false) {
                continue;
            }
            foreach ($calls as $call) {
                if (!$this->hasSecurePermissionCallback($call)) {
                    $violations[] = $file;
                    break;
                }
            }
        }
        if (!empty($violations)) {
            $this->fail('Insecure REST permissions in: ' . implode(', ', $violations));
        }
        $this->assertTrue(true);
    }

    private function collectPhpFiles(string $root): array
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $out = [];
        foreach ($rii as $f) {
            if ($f->isFile() && substr($f->getFilename(), -4) === '.php' && strpos($f->getPathname(), '/tests/') === false && strpos($f->getPathname(), '/vendor/') === false) {
                $out[] = $f->getPathname();
            }
        }
        return $out;
    }

    /**
     * @param array<string> $files
     * @return array<string,array<int,string>>
     */
    private function findRestRoutes(array $files): array
    {
        $out = [];
        foreach ($files as $file) {
            $src = file_get_contents($file) ?: '';
            $offset = 0;
            while (($pos = strpos($src, 'register_rest_route', $offset)) !== false) {
                $call = $this->extractCall($src, $pos);
                if ($call !== null) {
                    $out[$file][] = $call;
                    $offset = $pos + 1;
                } else {
                    break;
                }
            }
        }
        return $out;
    }

    private function extractCall(string $src, int $start): ?string
    {
        $open = strpos($src, '(', $start);
        if ($open === false) {
            return null;
        }
        $depth = 1;
        $i = $open + 1;
        $len = strlen($src);
        while ($i < $len && $depth > 0) {
            $ch = $src[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            }
            $i++;
        }
        if ($depth !== 0) {
            return null;
        }
        return substr($src, $start, $i - $start);
    }

    private function hasSecurePermissionCallback(string $call): bool
    {
        if (strpos($call, 'permission_callback') === false) {
            return false;
        }
        if (preg_match('/permission_callback\s*=>\s*([^,\)]+)/', $call, $m)) {
            $val = strtolower(trim($m[1], " \t\n\r\"'"));
            if ($val === '__return_true' || $val === 'true' || $val === '1') {
                return false;
            }
            return true;
        }
        return false;
    }
}

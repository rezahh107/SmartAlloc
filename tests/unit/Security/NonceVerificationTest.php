<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class NonceVerificationTest extends BaseTestCase
{
    public function test_nonce_usage_is_present_or_allowlisted(): void
    {
        if (getenv('RUN_SECURITY_TESTS') !== '1') {
            $this->markTestSkipped('security tests opt-in');
        }
        if (!class_exists('\\Brain\\Monkey\\Functions')) {
            $this->markTestSkipped('Brain Monkey not installed');
        }
        if (!function_exists('token_get_all')) {
            $this->markTestSkipped('tokenizer missing');
        }

        $files = $this->collectPhpFiles(dirname(__DIR__, 3));
        $handlers = $this->findSensitiveHandlers($files);

        if (count($handlers) === 0) {
            $this->markTestSkipped('no handlers found');
        }

        $violations = [];
        foreach ($handlers as $file) {
            $src = file_get_contents($file) ?: '';
            $hasAllowlist = strpos($src, '@security-ok-nonce') !== false;
            $hasNonce =
                preg_match('/wp_verify_nonce\s*\(/', $src) ||
                preg_match('/check_ajax_referer\s*\(/', $src) ||
                preg_match('/wp_nonce_field\s*\(/', $src);

            if (!$hasNonce && !$hasAllowlist) {
                $violations[] = $file;
            }
        }

        if (!empty($violations)) {
            $this->fail('Missing nonce verification in: ' . implode(', ', $violations));
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

    private function findSensitiveHandlers(array $files): array
    {
        $hits = [];
        foreach ($files as $file) {
            $src = file_get_contents($file) ?: '';
            if (
                preg_match("/add_action\s*\(\s*['\"](wp_ajax_|admin_post_)/", $src) ||
                strpos($src, 'rest_api_init') !== false
            ) {
                $hits[] = $file;
            }
        }
        return $hits;
    }
}

<?php

declare(strict_types=1);

namespace SmartAlloc\Debug;

use SmartAlloc\Infra\Metrics\MetricsCollector;
use function get_option;
use function glob;
use function is_array;
use function is_scalar;
use function is_dir;
use function json_encode;
use function mkdir;
use function sys_get_temp_dir;
use function time;
// WordPress functions are available at runtime.

final class ReproBuilder
{
    private RedactionAdapter $redactor;
    private MetricsCollector $metrics;
    private string $testDir;
    private string $bpDir;

    public function __construct(?RedactionAdapter $redactor = null, ?MetricsCollector $metrics = null, ?string $testDir = null, ?string $bpDir = null)
    {
        $this->redactor = $redactor ?? new RedactionAdapter();
        $this->metrics  = $metrics ?? new MetricsCollector();
        $this->testDir  = $testDir ?? dirname(__DIR__, 1) . '/../tests/Debug/Repro';
        $this->bpDir    = $bpDir   ?? dirname(__DIR__, 1) . '/../e2e/blueprints';
    }

    /**
     * Build PHPUnit repro scaffold and Playground blueprint for an error fingerprint.
     *
     * @return array{test:string,blueprint:string}
     */
    public function buildScaffold(string $fingerprint): array
    {
        $entry = $this->find($fingerprint);
        if (!$entry) {
            throw new \RuntimeException('Entry not found');
        }
        $entry = $this->redactor->redact($entry);

        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
        if (!is_dir($this->bpDir)) {
            mkdir($this->bpDir, 0777, true);
        }
        $testPath = $this->testDir . '/' . $fingerprint . 'Test.php';
        $bpPath   = $this->bpDir . '/error-' . $fingerprint . '.json';
        $class = 'Repro' . $fingerprint . 'Test';

        $ctx = is_array($entry['context'] ?? null) ? $entry['context'] : [];
        $route  = (string) ($ctx['route'] ?? '');
        $method = (string) ($ctx['method'] ?? 'GET');
        $arrange = sprintf('// Arrange: build minimal context for %s %s', $method, $route);
        $curl    = '';
        if (str_starts_with($route, '/wp-json')) {
            $curl = sprintf("// curl -X %s %s", $method, $route);
        }

        $test = <<<PHP
<?php

declare(strict_types=1);

namespace SmartAlloc\\Tests\\Debug\\Repro;

use PHPUnit\\Framework\\TestCase;

/**
 * @group repro
 * @large
 */
final class {$class} extends TestCase
{
    public function testRepro(): void
    {
        {$arrange}
        {$curl}
        \$this->markTestSkipped('Repro scaffold – fill TODOs and replace placeholders');
    }
}
PHP;
        file_put_contents($testPath, $test);

        $blueprint = [
            'wp' => [
                'plugins' => [
                    ['slug' => 'smartalloc', 'version' => defined('SMARTALLOC_VERSION') ? SMARTALLOC_VERSION : '0.0.0'],
                ],
                'options' => [],
            ],
            'smartalloc' => ['debug' => true],
        ];
        file_put_contents($bpPath, json_encode($blueprint, JSON_PRETTY_PRINT) . "\n");

        $this->metrics->inc('debug_repro_scaffold_created_total');
        return ['test' => $testPath, 'blueprint' => $bpPath];
    }

    /**
     * Build a sanitized debug bundle zip for the given fingerprint.
     */
    public function buildBundle(string $fingerprint): string
    {
        $entry = $this->find($fingerprint);
        if (!$entry) {
            throw new \RuntimeException('Entry not found');
        }
        $lockKey = 'smartalloc_debug_bundle_' . $fingerprint;
        if (get_transient($lockKey)) { // @phpstan-ignore-line
            throw new \DomainException('Rate limited');
        }
        set_transient($lockKey, 1, 3600); // @phpstan-ignore-line
        $entry = $this->redactor->redact($entry);
        $paths = $this->buildScaffold($fingerprint);

        $logs = array_slice(is_array($entry['breadcrumbs'] ?? null) ? $entry['breadcrumbs'] : [], -10);
        foreach ($logs as &$log) {
            $msg = isset($log['message']) && is_scalar($log['message']) ? (string) $log['message'] : '';
            $msg = (string) preg_replace('/\d{9,}/', '[redacted]', $msg);
            $log['message'] = $this->redactor->redact(['message' => $msg])['message'] ?? '';
        }
        unset($log);
        $entry['breadcrumbs'] = $logs;

        $upload = \wp_upload_dir(); // @phpstan-ignore-line
        $dir = rtrim($upload['basedir'] ?? sys_get_temp_dir(), '/') . '/smartalloc-debug';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $this->pruneOld($dir);
        $zipPath = $dir . '/debug-' . $fingerprint . '.zip';

        $prompt = (new PromptBuilder())->build($entry);
        $env = [
            'php' => PHP_VERSION,
            'wp'  => \get_bloginfo('version'), // @phpstan-ignore-line
        ];
        $tmp = sys_get_temp_dir() . '/sa-' . $fingerprint;
        if (!is_dir($tmp)) {
            mkdir($tmp);
        }
        file_put_contents($tmp . '/prompt.md', $prompt);
        copy($paths['test'], $tmp . '/' . basename($paths['test']));
        copy($paths['blueprint'], $tmp . '/blueprint.json');
        file_put_contents($tmp . '/logs.json', \wp_json_encode($logs)); // @phpstan-ignore-line
        file_put_contents($tmp . '/env.json', \wp_json_encode($env)); // @phpstan-ignore-line

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (['prompt.md', 'logs.json', 'env.json', 'blueprint.json', basename($paths['test'])] as $file) {
            $zip->addFile($tmp . '/' . $file, $file);
        }
        $zip->close();
        clearstatcache(true, $zipPath);
        $size = filesize($zipPath) ?: 0;
        if ($size > 1024 * 1024) {
            file_put_contents($tmp . '/logs.json', '[]');
            file_put_contents($tmp . '/prompt.md', (new PromptBuilder())->build(array_merge($entry, ['breadcrumbs' => []])));
            @unlink($zipPath);
            $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            foreach (['prompt.md', 'logs.json', 'env.json', 'blueprint.json', basename($paths['test'])] as $file) {
                $zip->addFile($tmp . '/' . $file, $file);
            }
            $zip->close();
            clearstatcache(true, $zipPath);
            $size = filesize($zipPath) ?: 0;
        }
        $this->metrics->inc('debug_bundle_created_total');
        $this->metrics->gauge('debug_bundle_last_size_bytes', (int) $size);
        return $zipPath;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function find(string $fingerprint): ?array
    {
        foreach (ErrorStore::all() as $entry) {
            /** @var array<string,mixed> $entry */
            $msg = isset($entry['message']) && is_scalar($entry['message']) ? (string) $entry['message'] : '';
            $file = isset($entry['file']) && is_scalar($entry['file']) ? (string) $entry['file'] : '';
            $line = isset($entry['line']) && is_scalar($entry['line']) ? (string) $entry['line'] : '';
            $hash = md5($msg . $file . $line);
            if ($hash === $fingerprint) {
                return $entry;
            }
        }
        return null;
    }

    private function pruneOld(string $dir): void
    {
        $files = glob($dir . '/*.zip') ?: [];
        foreach ($files as $file) {
            if (is_string($file) && @filemtime($file) !== false && filemtime($file) < time() - 7 * 24 * 3600) {
                @unlink($file);
            }
        }
    }
}

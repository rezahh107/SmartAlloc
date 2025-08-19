<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Debug\Repro;

use Brain\Monkey;
use Brain\Monkey\Functions;
use org\bovigo\vfs\vfsStream;
use SmartAlloc\Debug\ReproBuilder;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Infra\Metrics\MetricsCollector;

final class ReproBuilderTest extends BaseTestCase
{
    private MetricsCollector $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        vfsStream::setup('root');
        $GLOBALS['wp_upload_dir_basedir'] = sys_get_temp_dir();
        $this->metrics = new MetricsCollector();
        $this->metrics->reset();
        $GLOBALS['sa_transients'] = [];

        Functions\when('get_bloginfo')->alias(fn() => '6.0');
        Functions\when('wp_parse_url')->alias(fn($v) => parse_url($v));
        Functions\when('admin_url')->alias(fn($p = '') => 'https://example.com/wp-admin/' . ltrim($p, '/'));
        Functions\when('wp_create_nonce')->alias(fn($a) => 'good');
        Functions\when('wp_verify_nonce')->alias(fn($n, $a) => $n === 'good' && $a === 'smartalloc_debug_bundle');

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        $entry = ['message' => 'oops', 'file' => 'file.php', 'line' => 1, 'breadcrumbs' => [['message' => 'foo']]];
        $GLOBALS['sa_options'] = ['smartalloc_debug_errors' => [$entry]];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $GLOBALS['sa_options'] = [];
        unset($GLOBALS['wp_upload_dir_basedir']);
        parent::tearDown();
    }

    public function test_scaffold_bundle_and_metrics(): void
    {
        $finger = md5('oopsfile.php1');
        $builder = new ReproBuilder(null, $this->metrics, vfsStream::url('root/tests'), vfsStream::url('root/blueprints'));
        $paths = $builder->buildScaffold($finger);
        $this->assertFileExists($paths['test']);
        $this->assertFileExists($paths['blueprint']);
        $blueprint = json_decode((string) file_get_contents($paths['blueprint']), true);
        $this->assertSame(['wp', 'smartalloc'], array_keys($blueprint));
        $this->assertTrue($blueprint['smartalloc']['debug']);
        $plugin = $blueprint['wp']['plugins'][0];
        $this->assertArrayHasKey('slug', $plugin);
        $this->assertArrayHasKey('version', $plugin);
        $this->assertStringNotContainsString('/', $plugin['slug']);
        $metrics = $this->metrics->all();
        $this->assertSame(1, $metrics['counters']['debug_repro_scaffold_created_total'] ?? 0);

        $zip = $builder->buildBundle($finger);
        $this->assertFileExists($zip);
        $metrics = $this->metrics->all();
        $this->assertSame(1, $metrics['counters']['debug_bundle_created_total'] ?? 0);
        $this->assertSame(filesize($zip), $metrics['gauges']['debug_bundle_last_size_bytes'] ?? -1);
        $this->assertLessThan(1024 * 1024, filesize($zip));
    }

    public function test_rate_limit(): void
    {
        $finger = md5('oopsfile.php1');
        $builder = new ReproBuilder(null, $this->metrics, vfsStream::url('root/tests'), vfsStream::url('root/bp'));
        $builder->buildBundle($finger);
        $this->expectException(\DomainException::class);
        $builder->buildBundle($finger);
    }

    public function test_size_limit_truncates_logs(): void
    {
        $finger = md5('oopsfile.php1');
        $large = bin2hex(random_bytes(1024 * 1024));
        $GLOBALS['sa_options']['smartalloc_debug_errors'][0]['breadcrumbs'] = [['message' => $large]];
        $builder = new ReproBuilder(null, $this->metrics, vfsStream::url('root/tests'), vfsStream::url('root/bp'));
        $zip = $builder->buildBundle($finger);
        $zipObj = new \ZipArchive();
        $zipObj->open($zip);
        $logs = (string) $zipObj->getFromName('logs.json');
        $zipObj->close();
        $this->assertSame('[]', trim($logs));
        clearstatcache();
        $this->assertLessThan(1024 * 1024, filesize($zip));
    }
}

<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace {
    if (!class_exists('GFAPI')) {
        class GFAPI
        {
            /** @var mixed */
            public static $form;

            public static function set_form($form): void
            {
                self::$form = $form;
            }

            public static function get_form(int $id)
            {
                return self::$form;
            }
        }
    }
}

namespace SmartAlloc\Tests\Unit {

use PHPUnit\Framework\TestCase;
use SmartAlloc\Infra\GF\SchemaChecker;
use Brain\Monkey;
use Brain\Monkey\Functions;
use GFAPI;

class SchemaCheckerTest extends TestCase
{
    private SchemaChecker $checker;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->dir = sys_get_temp_dir() . '/smartalloc_' . uniqid();
        mkdir($this->dir . '/smartalloc/artifacts', 0755, true);
        Functions\when('wp_upload_dir')->justReturn(['basedir' => $this->dir]);
        Functions\when('__')->returnArg();
        Functions\when('esc_html__')->returnArg();
        $this->createSpec();
        $this->checker = new SchemaChecker();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        array_map('unlink', glob($this->dir . '/smartalloc/artifacts/*'));
        rmdir($this->dir . '/smartalloc/artifacts');
        rmdir($this->dir . '/smartalloc');
        rmdir($this->dir);
        parent::tearDown();
    }

    private function createSpec(): void
    {
        $spec = [
            'form_id' => 150,
            'fields' => [
                ['id' => 39, 'type' => 'text', 'required' => true, 'scoring' => ['complete_points' => 25]],
                ['id' => 20, 'type' => 'email', 'required' => true, 'scoring' => ['complete_points' => 25]],
                ['id' => 92, 'type' => 'phone', 'required' => true, 'scoring' => ['complete_points' => 25]],
                ['id' => 75, 'type' => 'checkbox', 'required' => true, 'scoring' => ['complete_points' => 25]],
            ],
        ];
        file_put_contents($this->dir . '/smartalloc/artifacts/SCHEMA_SPEC.json', json_encode($spec));
    }

    /** @test */
    public function it_validates_complete_correct_form(): void
    {
        GFAPI::set_form([
            'id' => 150,
            'fields' => [
                ['id' => 39, 'type' => 'text', 'isRequired' => true],
                ['id' => 20, 'type' => 'email', 'isRequired' => true],
                ['id' => 92, 'type' => 'phone', 'isRequired' => true],
                ['id' => 75, 'type' => 'checkbox', 'isRequired' => true],
            ],
        ]);
        $r = $this->checker->checkForm(150);
        $this->assertEquals('compatible', $r['status']);
        $this->assertEquals(100, $r['score']);
        $this->assertEmpty($r['issues']);
    }

    /**
     * @test
     * @dataProvider invalidForms
     */
    public function it_detects_schema_issues(array $form, string $status, int $score, array $kinds): void
    {
        GFAPI::set_form($form);
        $r = $this->checker->checkForm(150);
        $this->assertEquals($status, $r['status']);
        $this->assertEquals($score, $r['score']);
        $this->assertCount(count($kinds), $r['issues']);
        foreach ($kinds as $kind) {
            $this->assertContains($kind, array_column($r['issues'], 'kind'));
        }
    }

    public static function invalidForms(): array
    {
        return [
            'missing_fields' => [
                ['id' => 150, 'fields' => [
                    ['id' => 39, 'type' => 'text', 'isRequired' => true],
                    ['id' => 20, 'type' => 'email', 'isRequired' => true],
                ]],
                'critical', 50, ['missing', 'missing'],
            ],
            'wrong_types' => [
                ['id' => 150, 'fields' => [
                    ['id' => 39, 'type' => 'text', 'isRequired' => true],
                    ['id' => 20, 'type' => 'text', 'isRequired' => true],
                    ['id' => 92, 'type' => 'text', 'isRequired' => true],
                    ['id' => 75, 'type' => 'checkbox', 'isRequired' => true],
                ]],
                'critical', 50, ['wrong_type', 'wrong_type'],
            ],
            'not_required' => [
                ['id' => 150, 'fields' => [
                    ['id' => 39, 'type' => 'text', 'isRequired' => true],
                    ['id' => 20, 'type' => 'email', 'isRequired' => false],
                    ['id' => 92, 'type' => 'phone', 'isRequired' => true],
                    ['id' => 75, 'type' => 'checkbox', 'isRequired' => true],
                ]],
                'warning', 75, ['not_required'],
            ],
        ];
    }

    /** @test */
    public function it_throws_exception_for_unsupported_form_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->checker->checkForm(999);
    }

    /** @test */
    public function it_throws_exception_when_form_not_found(): void
    {
        GFAPI::set_form(false);
        $this->expectException(\RuntimeException::class);
        $this->checker->checkForm(150);
    }

    /** @test */
    public function it_handles_empty_form_fields(): void
    {
        GFAPI::set_form(['id' => 150, 'fields' => []]);
        $r = $this->checker->checkForm(150);
        $this->assertEquals('critical', $r['status']);
        $this->assertEquals(0, $r['score']);
        $this->assertCount(4, $r['issues']);
    }
}
}

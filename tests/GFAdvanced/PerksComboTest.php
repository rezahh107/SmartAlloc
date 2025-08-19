<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\GFAdvanced;

require_once dirname(__DIR__) . '/bootstrap.gf.php';

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;

final class PerksComboTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Functions::class)) {
            self::markTestSkipped('Brain Monkey unavailable');
        }
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_nested_forms_flatten_no_crash(): void
    {
        $parent = [
            'id' => 1,
            'children' => [
                ['id' => 11, 'name' => 'a'],
                ['id' => 12, 'name' => 'b'],
            ],
        ];

        $flatten = $this->flatten($parent);
        $expected = [
            'id' => 1,
            'children_0_id' => 11,
            'children_0_name' => 'a',
            'children_1_id' => 12,
            'children_1_name' => 'b',
        ];
        $this->assertSame($expected, $flatten);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix . $k;
            if (is_array($v)) {
                $out += $this->flatten($v, $key . '_');
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    }
}

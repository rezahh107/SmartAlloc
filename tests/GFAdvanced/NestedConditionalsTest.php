<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\GFAdvanced;

require_once dirname(__DIR__) . '/bootstrap.gf.php';

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;

final class NestedConditionalsTest extends BaseTestCase
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

    public function test_nested_conditionals_evaluator(): void
    {
        $form = [
            'fields' => [
                [
                    'id' => 1,
                    'conditionalLogic' => [
                        'logicType' => 'all',
                        'rules' => [
                            ['fieldId' => 2, 'operator' => '=', 'value' => 'show'],
                            [
                                'logicType' => 'any',
                                'rules' => [
                                    ['fieldId' => 3, 'operator' => '=', 'value' => 'on'],
                                    ['fieldId' => 4, 'operator' => '>', 'value' => 5],
                                ],
                            ],
                        ],
                    ],
                ],
                ['id' => 2],
                ['id' => 3],
                ['id' => 4],
            ],
        ];

        $cases = [
            ['values' => [2 => 'show', '3' => 'on', '4' => 0], 'expected' => [2, 3, 4, 1]],
            ['values' => [2 => 'hide', '3' => 'on', '4' => 10], 'expected' => [2, 3, 4]],
            ['values' => [2 => 'show', '3' => 'off', '4' => 10], 'expected' => [2, 3, 4, 1]],
        ];

        foreach ($cases as $case) {
            $this->assertSame($case['expected'], $this->visibleFields($form, $case['values']));
        }
    }

    /**
     * @param array<string,mixed> $values
     * @return array<int,int>
     */
    private function visibleFields(array $form, array $values): array
    {
        $visible = [];
        foreach ($form['fields'] as $field) {
            if (!isset($field['conditionalLogic']) || $this->evaluate($field['conditionalLogic'], $values)) {
                $visible[] = $field['id'];
            }
        }
        return $visible;
    }

    /**
     * @param array<string,mixed> $logic
     * @param array<string,mixed> $values
     */
    private function evaluate(array $logic, array $values): bool
    {
        $results = [];
        foreach ($logic['rules'] as $rule) {
            if (isset($rule['logicType'])) {
                $results[] = $this->evaluate($rule, $values);
                continue;
            }
            $val = $values[$rule['fieldId']] ?? null;
            $res = false;
            switch ($rule['operator']) {
                case '=':
                    $res = ($val === $rule['value']);
                    break;
                case '>':
                    $res = ($val > $rule['value']);
                    break;
            }
            $results[] = $res;
        }
        if (($logic['logicType'] ?? 'all') === 'all') {
            return !in_array(false, $results, true);
        }
        return in_array(true, $results, true);
    }
}

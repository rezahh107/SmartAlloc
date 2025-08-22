<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

final class GFFormGenerator
{
    public static function buildArray(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        return [
            'version' => '2.5.x',
            'forms' => [[
                'title' => 'SmartAlloc Registration',
                'description' => 'SmartAlloc compatible form',
                'date_created' => $now,
                'fields' => [
                    ['type' => 'text', 'label' => 'Student Mobile', 'adminLabel' => 'mobile', 'isRequired' => true],
                    ['type' => 'text', 'label' => 'National ID', 'adminLabel' => 'national_id', 'isRequired' => true],
                    ['type' => 'text', 'label' => 'Hekmat Tracking', 'adminLabel' => 'hekmat_tracking', 'isRequired' => false],
                    ['type' => 'select', 'label' => 'Gender', 'adminLabel' => 'gender', 'choices' => [['text' => 'Male'], ['text' => 'Female']], 'isRequired' => true],
                    ['type' => 'text', 'label' => 'School Name', 'adminLabel' => 'school_name', 'isRequired' => false],
                    ['type' => 'text', 'label' => 'School Code', 'adminLabel' => 'school_code', 'isRequired' => false],
                    ['type' => 'text', 'label' => 'Postal Code', 'adminLabel' => 'postal_code', 'isRequired' => false],
                    ['type' => 'text', 'label' => 'Postal Code Alias', 'adminLabel' => 'postal_code_alias', 'isRequired' => false],
                    ['type' => 'select', 'label' => 'Group/Grade', 'adminLabel' => 'group_code', 'choices' => [['text' => 'G7'], ['text' => 'G8'], ['text' => 'G9']], 'isRequired' => true],
                    ['type' => 'text', 'label' => 'Center', 'adminLabel' => 'center', 'isRequired' => false],
                ],
                'notifications' => [],
                'confirmations' => [],
            ]],
        ];
    }

    public static function buildJson(): string
    {
        return wp_json_encode(self::buildArray());
    }
}

<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

use GFAPI;

class SchemaChecker
{
    private array $schemaSpec;

    public function __construct()
    {
        $this->loadSchemaSpec();
    }

    private function loadSchemaSpec(): void
    {
        $specPath = wp_upload_dir()['basedir'] . '/smartalloc/artifacts/SCHEMA_SPEC.json';

        if (!file_exists($specPath)) {
            throw new \RuntimeException(__('Schema specification file not found', 'smartalloc'));
        }

        $content = file_get_contents($specPath);
        $this->schemaSpec = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(__('Invalid schema specification JSON', 'smartalloc'));
        }
    }

    public function checkForm(int $formId): array
    {
        if ($formId !== 150) {
            throw new \InvalidArgumentException(__('Only form ID 150 is supported', 'smartalloc'));
        }

        $form = GFAPI::get_form($formId);
        if (!$form || !is_array($form)) {
            throw new \RuntimeException(sprintf(__('Form %d not found or invalid', 'smartalloc'), $formId));
        }

        return $this->validateFormStructure($form);
    }

    private function validateFormStructure(array $form): array
    {
        $result = [
            'status' => 'compatible',
            'score' => 0,
            'issues' => [],
            'timestamp' => current_time('mysql', true)
        ];

        $maxScore = 0;
        $currentScore = 0;

        foreach ($this->schemaSpec['fields'] as $fieldSpec) {
            $fieldId = (int) $fieldSpec['id'];
            $maxScore += $fieldSpec['scoring']['complete_points'];

            $formField = $this->findFormField($form, $fieldId);

            if (!$formField) {
                $result['issues'][] = [
                    'fieldId' => $fieldId,
                    'kind' => 'missing',
                    'details' => sprintf(__('Required field %d is missing', 'smartalloc'), $fieldId)
                ];
                continue;
            }

            if (!$this->validateFieldType($formField, $fieldSpec)) {
                $result['issues'][] = [
                    'fieldId' => $fieldId,
                    'kind' => 'wrong_type',
                    'details' => sprintf(
                        __('Field %d type mismatch: expected %s, got %s', 'smartalloc'),
                        $fieldId,
                        $fieldSpec['type'],
                        $formField['type']
                    )
                ];
                continue;
            }

            if ($fieldSpec['required'] && empty($formField['isRequired'])) {
                $result['issues'][] = [
                    'fieldId' => $fieldId,
                    'kind' => 'not_required',
                    'details' => sprintf(__('Field %d should be marked as required', 'smartalloc'), $fieldId)
                ];
                continue;
            }

            $currentScore += $fieldSpec['scoring']['complete_points'];
        }

        $result['score'] = $maxScore > 0 ? (int) round(($currentScore / $maxScore) * 100) : 0;

        if (!empty($result['issues'])) {
            $result['status'] = count($result['issues']) >= 2 ? 'critical' : 'warning';
        }

        return $result;
    }

    private function findFormField(array $form, int $fieldId): ?array
    {
        foreach ($form['fields'] ?? [] as $field) {
            if ((int) $field['id'] === $fieldId) {
                return $field;
            }
        }

        return null;
    }

    private function validateFieldType(array $formField, array $fieldSpec): bool
    {
        $typeMap = [
            'text' => ['text', 'name'],
            'email' => ['email'],
            'phone' => ['phone'],
            'select' => ['select'],
            'textarea' => ['textarea'],
            'checkbox' => ['checkbox']
        ];

        $allowedTypes = $typeMap[$fieldSpec['type']] ?? [$fieldSpec['type']];
        return in_array($formField['type'], $allowedTypes, true);
    }
}

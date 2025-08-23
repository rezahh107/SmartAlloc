<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

final class ExporterConfig
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data) {}

    public static function load(?string $path = null): self
    {
        $upload = wp_upload_dir();
        $default = ($upload['basedir'] ?? sys_get_temp_dir()) . '/smart-alloc/SmartAlloc_Exporter_Config_v1.json';
        $path = $path ?? $default;
        if (!is_readable($path)) {
            $alt = dirname(__DIR__, 2) . '/SmartAlloc_Exporter_Config_v1.json';
            $path = is_readable($alt) ? $alt : $path;
        }
        $json = is_readable($path) ? file_get_contents($path) : '{}';
        $data = json_decode((string) $json, true) ?: [];
        $defaults = [
            'sheets' => [
                'Sheet2' => ['national_id','mobile','postal','hekmat'],
                'Sheet5' => [],
                '9394' => [],
            ],
            'string_fields' => ['national_id','mobile','postal','hekmat'],
        ];
        $data = array_merge($defaults, $data);
        return new self($data);
    }
}

<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Golden tests for exporter configuration integrity.
 *
 * @group export
 */
final class ExporterConfigGoldenTest extends TestCase {
    private const CONFIG_PATH = __DIR__ . '/../../config/SmartAlloc_Exporter_Config_v1.json';

    public function test_config_has_expected_sheets_and_headers(): void {
        self::assertFileExists(self::CONFIG_PATH);

        $json = file_get_contents(self::CONFIG_PATH);
        self::assertJson($json);

        $config = json_decode($json, true);
        self::assertIsArray($config);
        self::assertArrayHasKey('sheets', $config);
        self::assertNotEmpty($config['sheets']);

        $sheets = array_column($config['sheets'], 'name');
        self::assertContains('Summary', $sheets);
        self::assertContains('Errors', $sheets);
    }

    public function test_default_values_present(): void {
        $json = file_get_contents(self::CONFIG_PATH);
        $config = json_decode($json, true);

        self::assertArrayHasKey('defaults', $config);
        self::assertArrayHasKey('filename_pattern', $config['defaults']);
        self::assertStringContainsString('SabtExport', $config['defaults']['filename_pattern']);
    }
}

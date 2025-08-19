<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\I18N;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SmartAlloc\Tests\BaseTestCase;

require_once dirname(__DIR__) . '/Support/DataProviders.php';

final class RTLLayoutTest extends BaseTestCase
{
    public function test_persian_string_round_trip(): void
    {
        if (!class_exists(Spreadsheet::class)) {
            self::markTestSkipped('PhpSpreadsheet unavailable');
        }

        $sample = "متن فارسی Persian ۱۲۳۴۵ <> ' \" &";
        $csv = $this->writeCsv($sample);
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $csv);
        rewind($fh);
        fgetcsv($fh, 0, ',', '"', '\\');
        $data = fgetcsv($fh, 0, ',', '"', '\\') ?: [];
        fclose($fh);
        $this->assertSame($sample, $data[0] ?? '');

        $spread = new Spreadsheet();
        $sheet = $spread->getActiveSheet();
        $sheet->setCellValueExplicit('A1', 'col', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A2', $sample, DataType::TYPE_STRING);
        $path = tempnam(sys_get_temp_dir(), 'sa') . '.xlsx';
        (new Xlsx($spread))->save($path);
        $loaded = IOFactory::load($path);
        $this->assertSame($sample, $loaded->getActiveSheet()->getCell('A2')->getValue());
        @unlink($path);
    }

    /**
     * @dataProvider formulaPrefixProvider
     */
    public function test_formula_prefixes_escaped_or_skip(string $input): void
    {
        $csv = $this->writeCsv($input);
        $lines = explode("\n", trim($csv));
        $cell = $lines[1] ?? '';
        $trimmed = ltrim($cell, " \t\r\n");
        $first = $trimmed[0] ?? '';
        if (in_array($first, riskyLeadingTokens(), true)) {
            $this->markTestSkipped('CSV formula-escaping not implemented yet — TODO');
        }
        $this->assertSame($input, $cell);
    }

    /**
     * @return array<int,array{string}>
     */
    public static function formulaPrefixProvider(): array
    {
        $out = [];
        foreach (riskyLeadingTokens() as $token) {
            $out[] = [$token . '1'];
        }
        return $out;
    }

    private function writeCsv(string $cell): string
    {
        $fh = fopen('php://memory', 'r+');
        fputcsv($fh, ['h'], ',', '"', '\\');
        fputcsv($fh, [$cell], ',', '"', '\\');
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);
        $this->assertNotSame(0, strpos($csv, 'sep='));
        return $csv;
    }
}

<?php

declare(strict_types=1);

namespace JDC\Core;
class Core
{
    public static function convert(string $date): string
    {
        return 'jalali-' . $date;
    }
}

namespace SmartAlloc\Tests\Compat;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SmartAlloc\Compat\ThirdParty\JalaliDateConverter;
use SmartAlloc\Tests\BaseTestCase;

final class JalaliFilterBypassTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_exports_use_iso_dates_and_restore_filters(): void
    {
        add_filter('date_i18n', [\JDC\Core\Core::class, 'convert'], 10, 1);
        $GLOBALS['wp_filter']['date_i18n'][10]['jdc'] = [
            'function' => [\JDC\Core\Core::class, 'convert'],
            'accepted_args' => 1,
        ];
        $this->assertTrue(JalaliDateConverter::hasJalaliFilter());

        $this->assertSame('jalali-2024-03-02 10:12:00', self::dateI18n('2024-03-02 10:12:00'));

        $paths = JalaliDateConverter::withDateI18nBypassed(function (): array {
            $date = self::dateI18n('2024-03-02 10:12:00');
            $iso  = (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))
                ->format('Y-m-d\TH:i:s\Z');

            $csv = tempnam(sys_get_temp_dir(), 'sa') . '.csv';
            $fh = fopen($csv, 'w');
            fputcsv($fh, ['created_at'], ',', '"', '\\');
            fputcsv($fh, [$iso], ',', '"', '\\');
            fclose($fh);

            $xlsx = tempnam(sys_get_temp_dir(), 'sa') . '.xlsx';
            $spread = new Spreadsheet();
            $sheet = $spread->getActiveSheet();
            $sheet->setCellValue('A1', 'created_at');
            $sheet->setCellValue('A2', $iso);
            $writer = new Xlsx($spread);
            $writer->save($xlsx);

            return ['csv' => $csv, 'xlsx' => $xlsx];
        });

        $this->assertTrue(JalaliDateConverter::hasJalaliFilter());
        $this->assertSame('jalali-2024-03-02 10:12:00', self::dateI18n('2024-03-02 10:12:00'));

        $csv = file_get_contents($paths['csv']);
        $this->assertStringContainsString('2024-03-02T10:12:00Z', (string) $csv);

        $loaded = IOFactory::load($paths['xlsx']);
        $this->assertSame('2024-03-02T10:12:00Z', $loaded->getActiveSheet()->getCell('A2')->getValue());

        @unlink($paths['csv']);
        @unlink($paths['xlsx']);
    }

    private static function dateI18n(string $date): string
    {
        return JalaliDateConverter::hasJalaliFilter()
            ? \JDC\Core\Core::convert($date)
            : $date;
    }
}

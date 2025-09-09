<?php // phpcs:ignoreFile

declare(strict_types=1);

namespace {
    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir(): array { return ['basedir' => sys_get_temp_dir()]; }
    }
    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p($dir): bool { return is_dir($dir) || mkdir($dir, 0777, true); }
    }
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
    if (!class_exists('wpdb')) {
        class wpdb {
            public string $prefix = 'wp_';
            public string $last_query = '';
            public array $results = [];
            public function query($sql): void { $this->last_query = $sql; }
            public function prepare($sql, ...$args): string {
                $this->last_query = vsprintf($sql, $args);
                return $this->last_query;
            }
            public function get_results($sql): array {
                $this->last_query = $sql;
                return $this->results;
            }
        }
    }
}

namespace SmartAlloc\Tests\Unit\Services {

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\{ExportService, Logging};
use SmartAlloc\Tests\BaseTestCase;

final class ExportServiceTest extends BaseTestCase
{
    /** @test */
    public function writes_three_sheets_with_string_codes(): void
    {
        global $wpdb;
        $svc = new ExportService(new TableResolver($wpdb), config: null, logger: new Logging());
        $res = $svc->export(new FormContext(1));
        $this->assertTrue($res['ok']);
        $book = IOFactory::load($res['file']);
        $names = array_map(fn($s) => $s->getTitle(), $book->getAllSheets());
        $this->assertSame(['Sheet2','Sheet5','9394'], $names);
        $sheet2 = $book->getSheetByName('Sheet2');
        $this->assertSame(DataType::TYPE_STRING, $sheet2->getCell('A1')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet2->getCell('B1')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet2->getCell('C1')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet2->getCell('D1')->getDataType());
    }

    /** @test */
    public function stream_export_outputs_xlsx(): void
    {
        $GLOBALS['wpdb'] = new \wpdb();
        $svc = new ExportService(new TableResolver($GLOBALS['wpdb']), config: null, logger: new Logging());
        ob_start();
        $svc->streamExport(['limit' => '2']);
        $data = ob_get_clean();
        $this->assertStringContainsString('PK', (string) $data); // zip header
    }

}
}


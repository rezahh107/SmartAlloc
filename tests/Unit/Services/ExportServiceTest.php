<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace {
    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir(): array { return ['basedir' => sys_get_temp_dir()]; }
    }
    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p($dir): bool { return @mkdir($dir, 0777, true); }
    }
    if (!function_exists('esc_html')) {
        function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('wp_die')) {
        function wp_die($m = '') { echo $m; }
    }
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($d = null, $s = null) { return ['success' => false, 'data' => $d]; }
    }
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
}

namespace SmartAlloc\Tests\Unit\Services {

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\{ExportService, Logging};
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Tests\Helpers\TestWpdb;

final class ExportServiceTest extends BaseTestCase
{
    /** @test */
    public function writes_three_sheets_with_string_codes(): void
    {
        $wpdbStub = new TestWpdb();
        $GLOBALS['wpdb'] = $wpdbStub;
        $tableResolver = new TableResolver($wpdbStub);
        $svc = new ExportService($tableResolver, config: null, logger: new Logging());
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
        $wpdbStub = new TestWpdb();
        $wpdbStub->results = [(object)['id' => 1, 'national_id' => '1', 'mobile' => '2', 'postal' => '3']];
        $GLOBALS['wpdb'] = $wpdbStub;
        $tableResolver = new TableResolver($wpdbStub);
        $svc = new ExportService($tableResolver, config: null, logger: new Logging());
        ob_start();
        $svc->streamExport(['limit' => '2']);
        $data = ob_get_clean();
        $this->assertStringContainsString('PK', (string) $data); // zip header
    }

}
}


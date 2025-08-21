<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoverageImportTest extends TestCase {
  public function test_import_parses_clover_and_writes_normalized_json(): void {
    if (getenv('RUN_ENFORCE') !== '1') {
      $this->markTestSkipped('opt-in');
    }
    $tmp = sys_get_temp_dir().'/clover-sample.xml';
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project timestamp="123">
    <metrics files="1" lines-valid="10" lines-covered="7"/>
    <file name="src/Foo.php">
      <metrics lines-valid="10" lines-covered="7"/>
    </file>
  </project>
</coverage>
XML;
    file_put_contents($tmp, $xml);
    @mkdir(__DIR__.'/../../../artifacts/coverage', 0777, true);
    $cmd = PHP_BINARY.' '.escapeshellarg(__DIR__.'/../../../scripts/coverage-import.php').' --clover='.escapeshellarg($tmp);
    exec($cmd, $o, $rc);
    $this->assertSame(0, $rc);
    $path = __DIR__.'/../../../artifacts/coverage/coverage.json';
    $this->assertFileExists($path);
    $j = json_decode((string)file_get_contents($path), true);
    $this->assertSame(10, $j['totals']['lines']);
    $this->assertSame(7,  $j['totals']['covered']);
    $this->assertSame(70.0, $j['totals']['pct']);
  }
}

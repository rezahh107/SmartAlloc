<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GAEnforcerCoverageTest extends TestCase {
  public function test_ga_profile_enforces_low_coverage(): void {
    if (getenv('RUN_ENFORCE') !== '1') $this->markTestSkipped('opt-in');
    @mkdir(__DIR__.'/../../../artifacts/coverage', 0777, true);
    $cov = [
      'source'=>'test','generatedAt'=>date('c'),
      'totals'=>['lines'=>100,'covered'=>10,'pct'=>10.0],'files'=>[]
    ];
    file_put_contents(__DIR__.'/../../../artifacts/coverage/coverage.json', json_encode($cov));

    $cmd = 'RUN_ENFORCE=1 '.PHP_BINARY.' '.escapeshellarg(__DIR__.'/../../../scripts/ga-enforcer.php').' --profile=ga --enforce --junit';
    exec($cmd, $o, $rc);
    $this->assertNotSame(0, $rc, 'GA enforcement should fail when coverage below GA threshold');
  }
}

<?php
use WP_UnitTestCase;

class BootTest extends WP_UnitTestCase {
  public function test_wp_boots(): void {
    $this->assertTrue( function_exists('do_action') );
  }
}

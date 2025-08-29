<?php
/**
 * Allocation policy config tests.
 *
 * @package SmartAlloc\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for allocation-policy.yml contract.
 */
final class AllocationPolicyConfigTest extends TestCase {
	/**
	 * Parsed YAML contents.
	 *
	 * @var array
	 */
	private array $yaml;

	/**
	 * Load policy file.
	 */
	protected function setUp(): void {
		$path = __DIR__ . '/../../allocation-policy.yml';
		if ( ! file_exists( $path ) ) {
			$this->markTestSkipped( 'allocation-policy.yml must exist' );
		}
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertNotFalse( $raw, 'policy file must be readable' );
		if ( ! function_exists( 'yaml_parse' ) ) {
			$this->markTestSkipped( 'yaml extension not available' );
		}
		$parsed = yaml_parse( $raw );
		$this->assertIsArray( $parsed, 'parsed policy must be an array' );
		$this->yaml = $parsed;
	}

	/**
	 * Validates accept/manual/reject boundaries.
	 */
	public function test_fuzzy_school_thresholds(): void {
		$fz = array_key_exists( 'fuzzy_school', $this->yaml ) ? $this->yaml['fuzzy_school'] : array();
		$this->assertGreaterThanOrEqual( 0.90, (float) ( $fz['accept_threshold'] ?? 0.0 ) );
		$this->assertEqualsWithDelta( 0.80, (float) ( $fz['manual_min'] ?? 0.0 ), 0.001 );
		$this->assertEqualsWithDelta( 0.89, (float) ( $fz['manual_max'] ?? 0.0 ), 0.011 );
	}

	/**
	 * Ensures capacity constant.
	 */
	public function test_default_capacity_is_60(): void {
		$cap = (int) ( $this->yaml['default_capacity'] ?? 0 );
		$this->assertSame( 60, $cap, 'default capacity must be 60' );
	}

	/**
	 * Verifies alias configuration.
	 */
	public function test_postal_code_alias_exists(): void {
		$aliases = $this->yaml['aliases'] ?? array();
		$this->assertIsArray( $aliases );
		$this->assertArrayHasKey( 'postal_code_alt', $aliases, 'postal code alias missing' );
	}
}

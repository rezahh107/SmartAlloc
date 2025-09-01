<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Scripts;

use SmartAlloc\Tests\BaseTestCase;

final class EvaluateScoresDefaultTest extends BaseTestCase {

		/**
		 * @dataProvider dimensionDefaultProvider
		 */
	public function test_default_thresholds_trigger_ci_failure( string $dimension, int $expectedMin ): void {
		$tmp = sys_get_temp_dir() . '/sa_eval_' . uniqid();
		mkdir( $tmp, 0777, true );
		$root = dirname( __DIR__, 2 );
		symlink( $root . '/vendor', $tmp . '/vendor' );
		symlink( $root . '/src', $tmp . '/src' );
		symlink( $root . '/tests', $tmp . '/tests' );
		$context                                 = array(
			'current_scores' => array(
				'security'         => 25,
				'logic'            => 25,
				'performance'      => 25,
				'readability'      => 25,
				'goal'             => 25,
				'weighted_percent' => 95.0,
			),
		);
		$context['current_scores'][ $dimension ] = $expectedMin - 1;
		file_put_contents( $tmp . '/ai_context.json', json_encode( $context ) );
		$cwd = getcwd();
		chdir( $tmp );
		exec( 'bash ' . escapeshellarg( $root . '/scripts/evaluate_scores.sh' ), $o, $s );
		chdir( $cwd );

		$this->assertSame( 1, $s );
		$ctx = json_decode( file_get_contents( $tmp . '/ai_context.json' ), true );
		$this->assertSame( $expectedMin - 1, (int) $ctx['ci_failure'][ $dimension ]['score'] );
		$this->assertSame( $expectedMin, (int) $ctx['ci_failure'][ $dimension ]['min'] );
	}

	public function dimensionDefaultProvider(): array {
		return array(
			array( 'logic', 18 ),
			array( 'performance', 19 ),
			array( 'readability', 19 ),
			array( 'goal', 18 ),
		);
	}
}

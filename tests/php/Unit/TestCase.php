<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base unit test case. Sets up Brain Monkey and default passthrough stubs so
 * pure classes that call WordPress functions do not fatal under unit tests.
 */
abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// `apply_filters( $tag, $value, ...$args )` -> returns $value (2nd arg).
		Monkey\Functions\when( 'apply_filters' )->returnArg( 2 );
		Monkey\Functions\when( '__' )->returnArg( 1 );
		Monkey\Functions\when( 'add_action' )->justReturn( true );
		Monkey\Functions\when( 'add_filter' )->justReturn( true );
		Monkey\Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Monkey\Functions\when( 'sanitize_textarea_field' )->returnArg( 1 );
		Monkey\Functions\when( 'sanitize_key' )->returnArg( 1 );
		Monkey\Functions\when( 'wp_json_encode' )->alias( static fn( $v ) => json_encode( $v ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}

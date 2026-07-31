<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\FeesIntegration;
use Brain\Monkey;

/**
 * Which shape the cart fees take. Itemized by default; a single line when the
 * shop asks for it, or when a site is rewriting the charged total — itemized
 * rows cannot honestly represent a total adjusted outside them.
 */
final class FeesIntegrationModeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'has_filter' )->justReturn( false );
	}

	public function test_itemized_by_default(): void {
		$this->assertTrue( FeesIntegration::is_itemized() );
	}

	public function test_single_line_when_the_shop_opts_out(): void {
		Monkey\Functions\when( 'apply_filters' )->alias(
			static fn( $tag, $value = null ) => 'clcf_itemized_fees' === $tag ? false : $value
		);
		$this->assertFalse( FeesIntegration::is_itemized() );
	}

	public function test_single_line_when_a_site_filters_the_total(): void {
		Monkey\Functions\when( 'has_filter' )->alias( static fn( $tag ) => 'clcf_fee_total' === $tag );
		$this->assertFalse( FeesIntegration::is_itemized() );
	}

	public function test_the_total_filter_wins_over_an_explicit_opt_in(): void {
		// Both present: the money filter decides, because itemized rows would
		// not add up to the total it produced.
		Monkey\Functions\when( 'has_filter' )->alias( static fn( $tag ) => 'clcf_fee_total' === $tag );
		Monkey\Functions\when( 'apply_filters' )->alias(
			static fn( $tag, $value = null ) => 'clcf_itemized_fees' === $tag ? true : $value
		);
		$this->assertFalse( FeesIntegration::is_itemized() );
	}
}

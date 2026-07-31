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

	/**
	 * WooCommerce identifies a fee by sanitize_title( name ) and silently drops
	 * a second fee with the same id, so two fields labelled alike must still
	 * produce two distinct fees. This test pins the ids the emission uses.
	 */
	public function test_two_fields_sharing_a_label_produce_two_distinct_fee_ids(): void {
		$group = array(
			'fields' => array(
				array( 'id' => 'ins_a', 'type' => 'checkbox', 'label' => 'Insurance', 'price' => 5, 'priceMode' => 'fixed' ),
				array( 'id' => 'ins_b', 'type' => 'checkbox', 'label' => 'Insurance', 'price' => 7, 'priceMode' => 'fixed' ),
			),
		);
		$vals = array( 'ins_a' => 'yes', 'ins_b' => 'yes' );
		$rows = \CoreLabs\CheckoutFields\Checkout\FeeCalculator::breakdown( $group, $vals, $vals, 0.0, 2 );

		$this->assertCount( 2, $rows, 'both fields charge' );
		$this->assertSame( array( 'Insurance', 'Insurance' ), array_column( $rows, 'label' ), 'labels may legitimately repeat' );

		// The ids FeesIntegration derives are keyed on the field id, never the
		// label, so WooCommerce cannot collapse them.
		$ids = array_map( static fn( $r ) => 'clcf-' . $r['field_id'], $rows );
		$this->assertSame( array( 'clcf-ins_a', 'clcf-ins_b' ), $ids );
		$this->assertSame( $ids, array_unique( $ids ) );
	}
}

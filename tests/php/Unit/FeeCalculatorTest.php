<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\FeeCalculator;
use CoreLabs\CheckoutFields\Fields\FieldSchema;

class FeeCalculatorTest extends TestCase {

	public function test_no_priced_fields_returns_zero(): void {
		$group = array( 'fields' => array( array( 'id' => 'c', 'type' => 'checkbox' ) ) );
		$this->assertSame( 0.0, FeeCalculator::total( $group, array( 'c' => 'yes' ), array( 'c' => 'yes' ) ) );
	}

	public function test_fixed_fee_when_engaged(): void {
		$group = array( 'fields' => array( array( 'id' => 'gift', 'type' => 'checkbox', 'price' => 10.0, 'priceMode' => 'fixed' ) ) );
		$this->assertSame( 10.0, FeeCalculator::total( $group, array( 'gift' => 'yes' ), array( 'gift' => 'yes' ) ) );
		$this->assertSame( 0.0, FeeCalculator::total( $group, array(), array() ) ); // unchecked
	}

	public function test_per_unit_multiplies_by_number(): void {
		$group = array( 'fields' => array( array( 'id' => 'n', 'type' => 'number', 'price' => 2.5, 'priceMode' => 'per_unit' ) ) );
		$this->assertSame( 7.5, FeeCalculator::total( $group, array( 'n' => '3' ), array( 'n' => '3' ) ) );
		$this->assertSame( 0.0, FeeCalculator::total( $group, array( 'n' => '0' ), array( 'n' => '0' ) ) ); // 0 not engaged
	}

	public function test_percent_of_base(): void {
		$group = array( 'fields' => array( array( 'id' => 'rush', 'type' => 'checkbox', 'price' => 10.0, 'priceMode' => 'percent' ) ) );
		$this->assertSame( 20.0, FeeCalculator::total( $group, array( 'rush' => 'yes' ), array( 'rush' => 'yes' ), 200.0 ) );
	}

	public function test_inactive_field_is_skipped(): void {
		$group = array(
			'fields' => array(
				array(
					'id'              => 'cod_fee',
					'type'            => 'checkbox',
					'price'           => 5.0,
					'priceMode'       => 'fixed',
					'conditions'      => array( array( 'field' => '@payment_method', 'operator' => 'is', 'value' => 'cod' ) ),
					'conditionMatch'  => 'all',
					'conditionAction' => 'show',
				),
			),
		);
		// Engaged but hidden (payment is bacs) -> no fee.
		$this->assertSame( 0.0, FeeCalculator::total( $group, array( 'cod_fee' => 'yes' ), array( 'cod_fee' => 'yes', '@payment_method' => 'bacs' ) ) );
		// Active (cod) + engaged -> fee.
		$this->assertSame( 5.0, FeeCalculator::total( $group, array( 'cod_fee' => 'yes' ), array( 'cod_fee' => 'yes', '@payment_method' => 'cod' ) ) );
	}

	public function test_schema_keeps_price_ungated(): void {
		$out = FieldSchema::normalize(
			array( 'fields' => array( array( 'id' => 'n', 'type' => 'number', 'price' => '3.5', 'priceMode' => 'per_unit' ) ) )
		);
		$this->assertSame( 3.5, $out['fields'][0]['price'] );
		$this->assertSame( 'per_unit', $out['fields'][0]['priceMode'] );

		$bad = FieldSchema::normalize(
			array( 'fields' => array( array( 'id' => 'x', 'type' => 'text', 'price' => '-4', 'priceMode' => 'bogus' ) ) )
		);
		$this->assertSame( 0.0, $bad['fields'][0]['price'] );
		$this->assertSame( 'fixed', $bad['fields'][0]['priceMode'] );
	}

	/* ── breakdown: the per-field rows both the charge and the display read ── */

	public function test_breakdown_returns_one_row_per_charging_field(): void {
		$g    = array(
			'fields' => array(
				array( 'id' => 'wrap', 'type' => 'checkbox', 'label' => 'Gift wrapping', 'price' => 8, 'priceMode' => 'fixed' ),
				array( 'id' => 'note', 'type' => 'text', 'label' => 'Note', 'price' => 0 ),
			),
		);
		$vals = array( 'wrap' => 'yes', 'note' => 'hi' );
		$rows = FeeCalculator::breakdown( $g, $vals, $vals, 0.0, 2 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'wrap', $rows[0]['field_id'] );
		$this->assertSame( 'Gift wrapping', $rows[0]['label'] );
		$this->assertSame( 8.0, $rows[0]['amount'] );
	}

	public function test_total_is_the_sum_of_the_rounded_rows(): void {
		$g    = array(
			'fields' => array(
				array( 'id' => 'a', 'type' => 'checkbox', 'label' => 'A', 'price' => 3.333, 'priceMode' => 'fixed' ),
				array( 'id' => 'b', 'type' => 'checkbox', 'label' => 'B', 'price' => 3.333, 'priceMode' => 'fixed' ),
			),
		);
		$vals = array( 'a' => 'yes', 'b' => 'yes' );
		$rows = FeeCalculator::breakdown( $g, $vals, $vals, 0.0, 2 );
		$this->assertSame( 3.33, $rows[0]['amount'] );
		$this->assertSame( 6.66, FeeCalculator::total( $g, $vals, $vals, 0.0 ) );
	}

	public function test_rows_rounding_to_zero_are_skipped(): void {
		$g    = array(
			'fields' => array( array( 'id' => 'tiny', 'type' => 'checkbox', 'label' => 'Tiny', 'price' => 0.4, 'priceMode' => 'percent' ) ),
		);
		$vals = array( 'tiny' => 'yes' );
		$this->assertSame( array(), FeeCalculator::breakdown( $g, $vals, $vals, 1.0, 2 ) );
	}

	public function test_a_priced_option_lifts_a_zero_priced_field(): void {
		$g    = array(
			'fields' => array(
				array(
					'id'        => 'speed',
					'type'      => 'select',
					'label'     => 'Shipping speed',
					'price'     => 0,
					'priceMode' => 'fixed',
					'options'   => array( 'Standard', array( 'label' => 'Overnight', 'price' => 12 ) ),
				),
			),
		);
		$vals = array( 'speed' => 'Overnight' );
		$rows = FeeCalculator::breakdown( $g, $vals, $vals, 0.0, 2 );
		$this->assertCount( 1, $rows, 'the field price is 0 but the option charges' );
		$this->assertSame( 12.0, $rows[0]['amount'] );
		$this->assertSame( 'Shipping speed: Overnight', $rows[0]['label'] );
	}

	public function test_row_label_omits_the_value_for_non_choice_fields(): void {
		$g    = array( 'fields' => array( array( 'id' => 'wrap', 'type' => 'checkbox', 'label' => 'Gift wrapping', 'price' => 8 ) ) );
		$vals = array( 'wrap' => 'yes' );
		$rows = FeeCalculator::breakdown( $g, $vals, $vals, 0.0, 2 );
		$this->assertSame( 'Gift wrapping', $rows[0]['label'] );
	}

	/**
	 * The shared PHP↔JS fixture — the same file tests/js/options.test.js runs.
	 *
	 * @dataProvider optionPricingProvider
	 * @param array<string,mixed> $case
	 */
	public function test_shared_option_pricing_fixtures( array $case ): void {
		$g    = array( 'fields' => array( $case['field'] ) );
		$vals = array( $case['field']['id'] => $case['value'] );
		$this->assertSame(
			(float) $case['expected'],
			FeeCalculator::total( $g, $vals, $vals, (float) $case['base'] ),
			$case['name']
		);
	}

	/** @return array<string,array{0:array}> */
	public function optionPricingProvider(): array {
		$raw   = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/option-pricing-cases.json' ), true );
		$cases = array();
		foreach ( $raw['cases'] as $c ) {
			$cases[ $c['name'] ] = array( $c );
		}
		return $cases;
	}
}

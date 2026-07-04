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
}

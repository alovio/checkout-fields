<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Fields\FieldOptions;

final class FieldOptionsTest extends TestCase {

	private function speeds(): array {
		return array(
			'id'      => 'speed',
			'type'    => 'select',
			'price'   => 0,
			'options' => array( 'Standard', array( 'label' => 'Express', 'price' => 10 ) ),
		);
	}

	public function test_label_reads_both_shapes(): void {
		$this->assertSame( 'Standard', FieldOptions::label( 'Standard' ) );
		$this->assertSame( 'Express', FieldOptions::label( array( 'label' => 'Express', 'price' => 10 ) ) );
	}

	public function test_price_only_counts_positive_numbers(): void {
		$this->assertSame( 10.0, FieldOptions::price( array( 'label' => 'a', 'price' => 10 ) ) );
		$this->assertSame( 0.0, FieldOptions::price( 'a' ) );
		$this->assertSame( 0.0, FieldOptions::price( array( 'label' => 'a', 'price' => -5 ) ) );
		$this->assertSame( 0.0, FieldOptions::price( array( 'label' => 'a', 'price' => 'free' ) ) );
	}

	public function test_labels_lists_both_shapes(): void {
		$this->assertSame( array( 'Standard', 'Express' ), FieldOptions::labels( $this->speeds() ) );
	}

	public function test_effective_price_prefers_the_option_then_the_field(): void {
		$f          = $this->speeds();
		$f['price'] = 4.0;
		$this->assertSame( 4.0, FieldOptions::effective_price( $f, 'Standard' ) );
		$this->assertSame( 10.0, FieldOptions::effective_price( $f, 'Express' ) );
		$this->assertSame( 4.0, FieldOptions::effective_price( $f, 'Unknown' ) );
	}

	public function test_options_never_price_a_non_choice_field(): void {
		$f = array( 'type' => 'text', 'price' => 5, 'options' => array( array( 'label' => 'gold', 'price' => 500 ) ) );
		$this->assertSame( 0.0, FieldOptions::price_for_value( $f, 'gold' ) );
		$this->assertSame( 5.0, FieldOptions::effective_price( $f, 'gold' ) );
	}

	public function test_duplicate_labels_resolve_to_the_first_match(): void {
		$f = array(
			'type'    => 'select',
			'options' => array( array( 'label' => 'A', 'price' => 3 ), array( 'label' => 'A', 'price' => 9 ) ),
		);
		$this->assertSame( 3.0, FieldOptions::effective_price( $f, 'A' ) );
	}

	public function test_non_scalar_value_never_matches(): void {
		$this->assertSame( 0.0, FieldOptions::price_for_value( $this->speeds(), array( 'Express' ) ) );
	}

	public function test_has_priced_options_and_range(): void {
		$this->assertTrue( FieldOptions::has_priced_options( $this->speeds() ) );
		$this->assertSame( array( 10.0, 10.0 ), FieldOptions::price_range( $this->speeds() ) );

		$plain = array( 'type' => 'select', 'options' => array( 'A', 'B' ) );
		$this->assertFalse( FieldOptions::has_priced_options( $plain ) );
		$this->assertSame( array( 0.0, 0.0 ), FieldOptions::price_range( $plain ) );
	}

	public function test_range_covers_the_field_price_fallback(): void {
		$f          = $this->speeds();
		$f['price'] = 4.0;
		$this->assertSame( array( 4.0, 10.0 ), FieldOptions::price_range( $f ) );
	}
}

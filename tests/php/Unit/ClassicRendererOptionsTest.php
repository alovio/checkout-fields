<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\ClassicRenderer;
use CoreLabs\CheckoutFields\Checkout\FeeCalculator;
use Brain\Monkey;
use ReflectionMethod;

/**
 * The checkout must never advertise an amount it will not charge, so these
 * assert the rendered option text AGAINST FeeCalculator rather than against a
 * hard-coded string.
 */
final class ClassicRendererOptionsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'wc_price' )->alias( static fn( $a ) => '<span>&#36;' . number_format( (float) $a, 2 ) . '</span>' );
		Monkey\Functions\when( 'wp_strip_all_tags' )->alias( static fn( $s ) => strip_tags( (string) $s ) );
		Monkey\Functions\when( 'esc_html' )->returnArg( 1 );
		Monkey\Functions\when( 'esc_attr' )->returnArg( 1 );
	}

	/** @param mixed $option */
	private function optionText( array $f, $option ): string {
		$m = new ReflectionMethod( ClassicRenderer::class, 'option_text' );
		$m->setAccessible( true );
		return (string) $m->invoke( null, $f, $option );
	}

	private function charge( array $f, string $value ): float {
		$g = array( 'fields' => array( $f ) );
		return FeeCalculator::total( $g, array( $f['id'] => $value ), array( $f['id'] => $value ), 0.0 );
	}

	private function mixed_field(): array {
		return array(
			'id'        => 'speed',
			'type'      => 'select',
			'label'     => 'Speed',
			'price'     => 4,
			'priceMode' => 'fixed',
			'options'   => array( 'Standard', array( 'label' => 'Express', 'price' => 10 ) ),
		);
	}

	public function test_every_option_advertises_what_it_charges(): void {
		$f = $this->mixed_field();
		foreach ( array( 'Standard', 'Express' ) as $value ) {
			$charged = $this->charge( $f, $value );
			$option  = 'Standard' === $value ? 'Standard' : array( 'label' => 'Express', 'price' => 10 );
			$this->assertStringContainsString(
				'$' . number_format( $charged, 2 ),
				$this->optionText( $f, $option ),
				"option “{$value}” must show the amount it is charged"
			);
		}
	}

	public function test_unpriced_field_keeps_bare_labels(): void {
		// The field's own price is stated once elsewhere; repeating it on every
		// option would be noise.
		$f = array( 'id' => 's', 'type' => 'select', 'price' => 4, 'priceMode' => 'fixed', 'options' => array( 'A', 'B' ) );
		$this->assertSame( 'A', $this->optionText( $f, 'A' ) );
	}

	public function test_percent_mode_reads_as_a_percentage(): void {
		$f = array(
			'id'        => 'rush',
			'type'      => 'radio',
			'price'     => 0,
			'priceMode' => 'percent',
			'options'   => array( array( 'label' => 'Express', 'price' => 10 ) ),
		);
		$this->assertSame( 'Express (+10%)', $this->optionText( $f, $f['options'][0] ) );
	}

	public function test_duplicate_labels_advertise_the_price_that_is_charged(): void {
		$f = array(
			'id'        => 'speed',
			'type'      => 'select',
			'price'     => 0,
			'priceMode' => 'fixed',
			'options'   => array( array( 'label' => 'A', 'price' => 3 ), array( 'label' => 'A', 'price' => 9 ) ),
		);
		$this->assertSame( 3.0, $this->charge( $f, 'A' ) );
		// The second option must not advertise 9 when 3 is what is billed.
		$this->assertSame( 'A (+$3.00)', $this->optionText( $f, $f['options'][1] ) );
	}

	public function test_suffix_filter_can_remove_the_price(): void {
		$f = array( 'id' => 's', 'type' => 'select', 'price' => 0, 'options' => array( array( 'label' => 'A', 'price' => 4 ) ) );
		Monkey\Functions\when( 'apply_filters' )->alias(
			static fn( $tag, $value = null ) => 'clcf_option_price_suffix' === $tag ? '' : $value
		);
		$this->assertSame( 'A', $this->optionText( $f, $f['options'][0] ) );
	}
}

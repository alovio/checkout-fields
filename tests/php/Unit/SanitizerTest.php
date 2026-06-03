<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\Sanitizer;

class SanitizerTest extends TestCase {

	private function group(): array {
		return array(
			'fields' => array(
				array( 'id' => 'note', 'type' => 'text' ),
				array( 'id' => 'qty', 'type' => 'number' ),
				array( 'id' => 'gift', 'type' => 'checkbox' ),
				array( 'id' => 'wrap', 'type' => 'select', 'options' => array( 'Paper', 'Foil' ) ),
				array( 'id' => 'head', 'type' => 'heading' ),
			),
		);
	}

	public function test_text_kept_when_present_dropped_when_empty(): void {
		$out = Sanitizer::sanitize( $this->group(), array( 'note' => 'hello' ) );
		$this->assertSame( 'hello', $out['note'] );

		$out2 = Sanitizer::sanitize( $this->group(), array( 'note' => '' ) );
		$this->assertArrayNotHasKey( 'note', $out2 );
	}

	public function test_number_cast_to_float(): void {
		$out = Sanitizer::sanitize( $this->group(), array( 'qty' => '3' ) );
		$this->assertSame( 3.0, $out['qty'] );
	}

	public function test_checkbox_only_when_set(): void {
		$this->assertSame( 'yes', Sanitizer::sanitize( $this->group(), array( 'gift' => '1' ) )['gift'] );
		$this->assertArrayNotHasKey( 'gift', Sanitizer::sanitize( $this->group(), array() ) );
	}

	public function test_select_membership_enforced(): void {
		$this->assertSame( 'Foil', Sanitizer::sanitize( $this->group(), array( 'wrap' => 'Foil' ) )['wrap'] );
		$this->assertArrayNotHasKey( 'wrap', Sanitizer::sanitize( $this->group(), array( 'wrap' => 'Hacked' ) ) );
	}

	public function test_heading_and_unknown_ids_ignored(): void {
		$out = Sanitizer::sanitize( $this->group(), array( 'head' => 'x', 'ghost' => 'y' ) );
		$this->assertArrayNotHasKey( 'head', $out );
		$this->assertArrayNotHasKey( 'ghost', $out );
	}
}

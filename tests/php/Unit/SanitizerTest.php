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

	public function test_delivery_combines_to_a_single_composite(): void {
		$group = array( 'fields' => array( array( 'id' => 'del', 'type' => 'delivery', 'timeSlots' => array( '9–12' ) ) ) );

		// Classic posts an array; Block posts the composite string — both become "date|slot".
		$classic = Sanitizer::sanitize( $group, array( 'del' => array( 'date' => '2026-09-10', 'slot' => '9–12' ) ) );
		$this->assertSame( '2026-09-10|9–12', $classic['del'] );

		$block = Sanitizer::sanitize( $group, array( 'del' => '2026-09-10|9–12' ) );
		$this->assertSame( '2026-09-10|9–12', $block['del'] );

		// A stray separator inside the slot can't corrupt the value.
		$dirty = Sanitizer::sanitize( $group, array( 'del' => array( 'date' => '2026-09-10', 'slot' => 'a|b' ) ) );
		$this->assertSame( '2026-09-10|ab', $dirty['del'] );

		// Nothing chosen ⇒ not stored.
		$empty = Sanitizer::sanitize( $group, array( 'del' => '|' ) );
		$this->assertArrayNotHasKey( 'del', $empty );
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

	public function test_new_types_sanitized_by_kind(): void {
		\Brain\Monkey\Functions\when( 'sanitize_email' )->alias( static fn( $v ) => filter_var( $v, FILTER_SANITIZE_EMAIL ) );
		\Brain\Monkey\Functions\when( 'esc_url_raw' )->alias( static fn( $v ) => filter_var( $v, FILTER_SANITIZE_URL ) );
		$group = array(
			'fields' => array(
				array( 'id' => 'em', 'type' => 'email' ),
				array( 'id' => 'ph', 'type' => 'phone' ),
				array( 'id' => 'ur', 'type' => 'url' ),
				array( 'id' => 'dt', 'type' => 'date' ),
				array( 'id' => 'tm', 'type' => 'time' ),
			),
		);
		$out = Sanitizer::sanitize( $group, array(
			'em' => ' user@example.com ',
			'ph' => '+994 50 123 45 67',
			'ur' => 'https://alovio.org/x',
			'dt' => '2026-08-01',
			'tm' => '14:30',
		) );
		$this->assertSame( 'user@example.com', $out['em'] );
		$this->assertSame( '+994 50 123 45 67', $out['ph'] );
		$this->assertSame( 'https://alovio.org/x', $out['ur'] );
		$this->assertSame( '2026-08-01', $out['dt'] );
		$this->assertSame( '14:30', $out['tm'] );
	}

	public function test_priced_options_are_accepted_by_their_label(): void {
		$g = array(
			'fields' => array(
				array(
					'id'      => 'speed',
					'type'    => 'select',
					'options' => array( 'Standard', array( 'label' => 'Express', 'price' => 10 ) ),
				),
			),
		);
		$this->assertSame( array( 'speed' => 'Express' ), Sanitizer::sanitize( $g, array( 'speed' => 'Express' ) ) );
		// The rendered text ("Express (+$10.00)") must never validate as a value.
		$this->assertSame( array(), Sanitizer::sanitize( $g, array( 'speed' => 'Express (+$10.00)' ) ) );
	}

	public function test_file_field_keeps_every_token(): void {
		$a = str_repeat( 'a1', 16 );
		$b = str_repeat( 'b2', 16 );
		$g = array( 'fields' => array( array( 'id' => 'art', 'type' => 'file', 'maxFiles' => 3 ) ) );

		$this->assertSame( array( 'art' => $a ), Sanitizer::sanitize( $g, array( 'art' => $a ) ) );
		// A multi-file value must survive: it used to fail the single-token
		// regex and the whole field was dropped before it reached the order.
		$this->assertSame( array( 'art' => "$a,$b" ), Sanitizer::sanitize( $g, array( 'art' => "$a,$b" ) ) );
		$this->assertSame( array(), Sanitizer::sanitize( $g, array( 'art' => 'not-a-token' ) ) );
	}

	public function test_file_field_is_capped_at_max_files(): void {
		$a = str_repeat( 'a1', 16 );
		$b = str_repeat( 'b2', 16 );
		$c = str_repeat( 'c3', 16 );
		$g = array( 'fields' => array( array( 'id' => 'art', 'type' => 'file', 'maxFiles' => 2 ) ) );
		$this->assertSame( array( 'art' => "$a,$b" ), Sanitizer::sanitize( $g, array( 'art' => "$a,$b,$c" ) ) );
	}
}

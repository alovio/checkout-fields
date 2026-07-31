<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\Validator;

class ValidatorTest extends TestCase {

	public function test_required_field_empty_errors(): void {
		$group  = array( 'fields' => array( array( 'id' => 'note', 'type' => 'text', 'label' => 'Note', 'required' => true ) ) );
		$errors = Validator::validate( $group, array(), array() );
		$this->assertNotEmpty( $errors );
	}

	public function test_required_field_filled_passes(): void {
		$group  = array( 'fields' => array( array( 'id' => 'note', 'type' => 'text', 'label' => 'Note', 'required' => true ) ) );
		$errors = Validator::validate( $group, array( 'note' => 'hi' ), array( 'note' => 'hi' ) );
		$this->assertSame( array(), $errors );
	}

	public function test_hidden_required_field_is_not_validated(): void {
		// "vat" only shows for the customer role; for a guest it is inactive → skipped.
		$group = array(
			'fields' => array(
				array(
					'id'              => 'vat',
					'type'            => 'text',
					'label'           => 'VAT',
					'required'        => true,
					'conditions'      => array( array( 'field' => '@user_role', 'operator' => 'is', 'value' => 'customer' ) ),
					'conditionMatch'  => 'all',
					'conditionAction' => 'show',
				),
			),
		);
		$this->assertSame( array(), Validator::validate( $group, array(), array( '@user_role' => 'guest' ) ) );
		$this->assertNotEmpty( Validator::validate( $group, array(), array( '@user_role' => 'customer' ) ) );
	}

	public function test_number_must_be_numeric(): void {
		$group = array( 'fields' => array( array( 'id' => 'n', 'type' => 'number', 'label' => 'Count' ) ) );
		$this->assertNotEmpty( Validator::validate( $group, array( 'n' => 'abc' ), array( 'n' => 'abc' ) ) );
		$this->assertSame( array(), Validator::validate( $group, array( 'n' => '5' ), array( 'n' => '5' ) ) );
	}

	public function test_select_invalid_option_errors(): void {
		$group = array( 'fields' => array( array( 'id' => 'w', 'type' => 'select', 'label' => 'Wrap', 'options' => array( 'Paper', 'Foil' ) ) ) );
		$this->assertNotEmpty( Validator::validate( $group, array( 'w' => 'Hacked' ), array( 'w' => 'Hacked' ) ) );
		$this->assertSame( array(), Validator::validate( $group, array( 'w' => 'Paper' ), array( 'w' => 'Paper' ) ) );
	}

	public function test_email_url_date_time_validation(): void {
		\Brain\Monkey\Functions\when( 'is_email' )->alias( static fn( $v ) => (bool) filter_var( $v, FILTER_VALIDATE_EMAIL ) );
		$group = array(
			'fields' => array(
				array( 'id' => 'em', 'type' => 'email', 'label' => 'Email' ),
				array( 'id' => 'ur', 'type' => 'url', 'label' => 'URL' ),
				array( 'id' => 'dt', 'type' => 'date', 'label' => 'Date', 'min' => '2026-01-01', 'max' => '2026-12-31' ),
				array( 'id' => 'tm', 'type' => 'time', 'label' => 'Time' ),
			),
		);
		$ok = array( 'em' => 'a@b.com', 'ur' => 'https://alovio.org', 'dt' => '2026-06-15', 'tm' => '14:30' );
		$this->assertSame( array(), Validator::validate( $group, $ok, $ok ) );

		$bad = array( 'em' => 'not-an-email', 'ur' => 'not a url', 'dt' => '2027-01-01', 'tm' => '25:99' );
		$errors = Validator::validate( $group, $bad, $bad );
		$this->assertCount( 4, $errors );
	}

	public function test_conditional_require_via_payment_method(): void {
		// "agree" becomes required only when paying COD.
		$group = array(
			'fields' => array(
				array(
					'id'              => 'agree',
					'type'            => 'checkbox',
					'label'           => 'Agree',
					'conditions'      => array( array( 'field' => '@payment_method', 'operator' => 'is', 'value' => 'cod' ) ),
					'conditionMatch'  => 'all',
					'conditionAction' => 'require',
				),
			),
		);
		$this->assertNotEmpty( Validator::validate( $group, array(), array( '@payment_method' => 'cod' ) ) );
		$this->assertSame( array(), Validator::validate( $group, array(), array( '@payment_method' => 'bacs' ) ) );
	}

	public function test_priced_options_validate_on_the_bare_label(): void {
		$g = array(
			'fields' => array(
				array(
					'id'      => 'speed',
					'type'    => 'select',
					'label'   => 'Speed',
					'options' => array( 'Standard', array( 'label' => 'Express', 'price' => 10 ) ),
				),
			),
		);
		$this->assertSame( array(), Validator::validate( $g, array( 'speed' => 'Express' ), array( 'speed' => 'Express' ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 'speed' => 'Nope' ), array( 'speed' => 'Nope' ) ) );
	}
}

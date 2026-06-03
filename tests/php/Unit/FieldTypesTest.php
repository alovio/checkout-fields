<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Fields\FieldTypes;

class FieldTypesTest extends TestCase {

	public function test_free_types_are_valid(): void {
		foreach ( FieldTypes::FREE as $type ) {
			$this->assertTrue( FieldTypes::is_valid( $type ), $type );
		}
	}

	public function test_unknown_type_is_invalid(): void {
		$this->assertFalse( FieldTypes::is_valid( 'rocket' ) );
	}

	public function test_pro_types_invalid_when_gate_off(): void {
		// `file`/`date` are Pro-only and not in the free set (gate off in unit tests).
		$this->assertFalse( FieldTypes::is_valid( 'file' ) );
		$this->assertFalse( FieldTypes::is_valid( 'date' ) );
	}

	public function test_all_defaults_to_free_set(): void {
		$this->assertSame( FieldTypes::FREE, FieldTypes::all() );
	}
}

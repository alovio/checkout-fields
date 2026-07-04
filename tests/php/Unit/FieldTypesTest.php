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

	public function test_new_types_are_valid(): void {
		foreach ( array( 'email', 'phone', 'url', 'date', 'time' ) as $type ) {
			$this->assertTrue( FieldTypes::is_valid( $type ), $type );
		}
	}

	public function test_file_is_valid(): void {
		$this->assertTrue( FieldTypes::is_valid( 'file' ) );
	}

	public function test_all_defaults_to_free_set(): void {
		$this->assertSame( FieldTypes::FREE, FieldTypes::all() );
	}
}

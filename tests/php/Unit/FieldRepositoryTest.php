<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Fields\FieldRepository;
use Brain\Monkey;

class FieldRepositoryTest extends TestCase {

	public function test_get_returns_normalized_empty_when_absent(): void {
		Monkey\Functions\when( 'get_option' )->justReturn( '' );

		$group = ( new FieldRepository() )->get();

		$this->assertSame( 1, $group['version'] );
		$this->assertSame( array(), $group['fields'] );
	}

	public function test_get_decodes_stored_json(): void {
		$stored = json_encode( array( 'version' => 1, 'fields' => array( array( 'id' => 'note', 'type' => 'text' ) ) ) );
		Monkey\Functions\when( 'get_option' )->justReturn( $stored );

		$group = ( new FieldRepository() )->get();

		$this->assertSame( 'note', $group['fields'][0]['id'] );
	}

	public function test_save_normalizes_and_persists_json(): void {
		$captured = null;
		Monkey\Functions\when( 'update_option' )->alias(
			static function ( $key, $value ) use ( &$captured ) {
				$captured = $value;
				return true;
			}
		);

		$out = ( new FieldRepository() )->save(
			array(
				'fields' => array(
					array( 'id' => 'a', 'type' => 'text', 'label' => 'A' ),
					array( 'id' => 'x', 'type' => 'bogus' ),
				),
			)
		);

		$this->assertCount( 1, $out['fields'] );
		$this->assertSame( 'a', $out['fields'][0]['id'] );
		$this->assertIsString( $captured );
		$decoded = json_decode( $captured, true );
		$this->assertSame( 'a', $decoded['fields'][0]['id'] );
	}
}

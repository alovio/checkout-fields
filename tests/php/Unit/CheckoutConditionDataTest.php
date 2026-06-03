<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\CheckoutConditionData;

class CheckoutConditionDataTest extends TestCase {

	/**
	 * Parity contract shared with the JS mirror (data-sources.js buildValues).
	 *
	 * @dataProvider fixtureCases
	 * @param array<string,mixed>  $field_values
	 * @param array<string,mixed>  $ctx
	 * @param array<string,string> $expected
	 */
	public function test_from_values_matches_fixture( string $name, array $field_values, array $ctx, array $expected ): void {
		$this->assertEquals( $expected, CheckoutConditionData::from_values( $field_values, $ctx ), $name );
	}

	/** @return array<int,array{0:string,1:array,2:array,3:array}> */
	public function fixtureCases(): array {
		$path  = dirname( __DIR__, 2 ) . '/fixtures/condition-data-cases.json';
		$cases = json_decode( (string) file_get_contents( $path ), true );
		return array_map(
			static fn( $c ) => array( $c['name'], $c['fieldValues'], $c['ctx'], $c['expected'] ),
			$cases
		);
	}

	public function test_non_scalar_field_value_becomes_empty_string(): void {
		$out = CheckoutConditionData::from_values( array( 'a' => array( 'x' ), 'b' => 'ok' ), array( 'role' => 'customer' ) );
		$this->assertSame( '', $out['a'] );
		$this->assertSame( 'ok', $out['b'] );
		$this->assertSame( 'customer', $out['@user_role'] );
	}
}

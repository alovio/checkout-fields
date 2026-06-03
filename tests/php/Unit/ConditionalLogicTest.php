<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Logic\ConditionalLogic;

class ConditionalLogicTest extends TestCase {

	/**
	 * Parity contract shared with the JS evaluator.
	 *
	 * @dataProvider fixtureCases
	 */
	public function test_is_active_matches_fixture( string $name, $condition, array $values, bool $expected ): void {
		$field = array( 'condition' => $condition );
		$this->assertSame( $expected, ConditionalLogic::is_active( $field, $values ), $name );
	}

	/** @return array<int,array{0:string,1:mixed,2:array,3:bool}> */
	public function fixtureCases(): array {
		$path  = dirname( __DIR__, 2 ) . '/fixtures/conditional-cases.json';
		$cases = json_decode( (string) file_get_contents( $path ), true );
		return array_map(
			static fn( $c ) => array( $c['name'], $c['condition'], $c['values'], $c['expectedActive'] ),
			$cases
		);
	}
}

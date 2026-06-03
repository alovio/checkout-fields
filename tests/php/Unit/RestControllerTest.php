<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Admin\RestController;
use Brain\Monkey;

class RestControllerTest extends TestCase {

	public function test_can_manage_requires_manage_woocommerce_cap(): void {
		Monkey\Functions\expect( 'current_user_can' )->once()->with( 'manage_woocommerce' )->andReturn( true );
		$this->assertTrue( ( new RestController() )->can_manage() );
	}

	public function test_save_fields_persists_and_returns_normalized(): void {
		Monkey\Functions\when( 'get_option' )->justReturn( '' );
		Monkey\Functions\when( 'update_option' )->justReturn( true );
		Monkey\Functions\when( 'rest_ensure_response' )->returnArg( 1 );

		$request = new class() {
			/** @return mixed */
			public function get_param( string $key ) {
				return 'fields' === $key
					? array(
						array( 'id' => 'note', 'type' => 'text' ),
						array( 'id' => 'bad', 'type' => 'bogus' ),
					)
					: null;
			}
		};

		$out = ( new RestController() )->save_fields( $request );

		$this->assertCount( 1, $out['fields'] );
		$this->assertSame( 'note', $out['fields'][0]['id'] );
	}
}

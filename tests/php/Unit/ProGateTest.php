<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Pro\ProModule;
use Brain\Monkey;

class ProGateTest extends TestCase {

	public function test_is_pro_defaults_false(): void {
		$this->assertFalse( ProModule::is_pro() );
	}

	public function test_is_pro_true_when_filter_on(): void {
		Monkey\Functions\when( 'apply_filters' )->alias(
			static fn( $tag, $value ) => 'clcf_is_pro' === $tag ? true : $value
		);
		$this->assertTrue( ProModule::is_pro() );
	}

	public function test_field_types_adds_file_and_date(): void {
		$out = ( new ProModule() )->field_types( array( 'text', 'select' ) );
		$this->assertContains( 'text', $out );
		$this->assertContains( 'file', $out );
		$this->assertContains( 'date', $out );
	}
}

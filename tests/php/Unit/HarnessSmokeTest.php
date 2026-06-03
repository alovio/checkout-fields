<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

/**
 * Confirms the unit-test harness (autoloader + Brain Monkey stubs) is wired up.
 * Real subsystem tests replace this baseline as chunks land.
 */
class HarnessSmokeTest extends TestCase {

	public function test_apply_filters_passthrough_stub(): void {
		$this->assertSame( 'value', apply_filters( 'clcf_anything', 'value' ) );
	}

	public function test_autoloader_is_available(): void {
		$this->assertTrue( function_exists( 'Brain\\Monkey\\setUp' ) );
	}
}

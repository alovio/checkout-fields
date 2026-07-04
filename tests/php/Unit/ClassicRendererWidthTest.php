<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\ClassicRenderer;
use CoreLabs\CheckoutFields\Fields\FieldRepository;
use Brain\Monkey;

class ClassicRendererWidthTest extends TestCase {

	private function renderer_with( array $fields ): ClassicRenderer {
		// FieldRepository is final — feed it through the option it reads.
		Monkey\Functions\when( 'get_option' )->justReturn( json_encode( array( 'version' => 1, 'fields' => $fields ) ) );
		return new ClassicRenderer( new FieldRepository() );
	}

	protected function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'esc_attr' )->returnArg( 1 );
		Monkey\Functions\when( 'esc_html' )->returnArg( 1 );
		Monkey\Functions\when( 'esc_textarea' )->returnArg( 1 );
		Monkey\Functions\when( 'esc_attr__' )->returnArg( 1 );
		Monkey\Functions\when( 'esc_html__' )->returnArg( 1 );
		Monkey\Functions\when( 'wp_get_current_user' )->justReturn( null );
	}

	public function test_region_renders_grid_wrapper_and_width_classes(): void {
		$renderer = $this->renderer_with(
			array(
				array( 'id' => 'a', 'type' => 'text', 'label' => 'A', 'width' => 'half', 'location' => 'after_order_notes' ),
				array( 'id' => 'b', 'type' => 'text', 'label' => 'B', 'width' => 'full', 'location' => 'after_order_notes' ),
			)
		);

		ob_start();
		// render_region is private-ish via hook closures; call through the public hook path:
		// register() wires closures, so expose by invoking render for the region via reflection.
		$m = new \ReflectionMethod( ClassicRenderer::class, 'render_region' );
		$m->setAccessible( true );
		$m->invoke( $renderer, 'after_order_notes' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'clcf-fields clcf-grid', $html );
		$this->assertStringContainsString( 'clcf-w-half', $html );
		$this->assertStringContainsString( 'clcf-w-full', $html );
	}
}

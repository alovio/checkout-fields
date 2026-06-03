<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap singleton. `boot()` wires subsystems and is idempotent.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// The Pro module is shipped separately (excluded from the free build); load it only when present.
		if ( class_exists( \CoreLabs\CheckoutFields\Pro\ProModule::class ) ) {
			( new \CoreLabs\CheckoutFields\Pro\ProModule() )->register();
		}

		( new \CoreLabs\CheckoutFields\Admin\RestController() )->register();
		( new \CoreLabs\CheckoutFields\Admin\SettingsPage() )->register();
		( new \CoreLabs\CheckoutFields\Admin\BuilderAssets() )->register();

		( new \CoreLabs\CheckoutFields\Checkout\ClassicRenderer() )->register();
		( new \CoreLabs\CheckoutFields\Checkout\OrderPersistence() )->register();
		( new \CoreLabs\CheckoutFields\Checkout\BlockIntegration() )->register();
		( new \CoreLabs\CheckoutFields\Checkout\FeesIntegration() )->register();
		( new \CoreLabs\CheckoutFields\Frontend\FrontendAssets() )->register();
	}
}

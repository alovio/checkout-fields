<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use CoreLabs\CheckoutFields\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Block-checkout integration (Store API). Built on the Task 5.0 spike findings:
 *  - enqueue a React bundle via IntegrationInterface (+ inline window.CLCF_BLOCK
 *    config: the field group, source labels, and the server context);
 *  - accept posted values under extensions.clcf.fields via the Store API;
 *  - validate (throw RouteException) and persist (OrderPersistence) on order create.
 *
 * Client lives in src/block-checkout/index.js; conditions resolve live there from
 * window.CLCF_BLOCK.ctx + the wc/store/* data stores. The server re-derives the
 * context authoritatively here for validation/persistence.
 */
final class BlockIntegration {

	private FieldRepository $repo;

	public function __construct( ?FieldRepository $repo = null ) {
		$this->repo = $repo ?? new FieldRepository();
	}

	public function register(): void {
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			static function ( $registry ) {
				$registry->register( new BlockFieldsIntegration() );
			}
		);

		add_action( 'woocommerce_blocks_loaded', static function () {
			if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
				return;
			}
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => 'checkout',
					'namespace'       => 'clcf',
					'schema_callback' => static function () {
						return array(
							'fields' => array(
								'description' => 'Submitted checkout-field values keyed by field id.',
								'type'        => array( 'object', 'null' ),
								'context'     => array( 'view', 'edit' ),
								'optional'    => true,
							),
						);
					},
					'schema_type'     => ARRAY_A,
				)
			);

			// Expose the dynamic context on the cart endpoint so the Block client
			// can resolve role/cart-category conditions live (read-only).
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => 'cart',
					'namespace'       => 'clcf',
					'data_callback'   => static function () {
						return array( 'ctx' => CheckoutConditionData::context() );
					},
					'schema_callback' => static function () {
						return array(
							'ctx' => array(
								'description' => 'CLCF checkout context for conditional logic.',
								'type'        => 'object',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
						);
					},
					'schema_type'     => ARRAY_A,
				)
			);
		} );

		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'update_order_from_request' ), 10, 2 );
	}

	/**
	 * @param \WC_Order         $order
	 * @param \WP_REST_Request  $request
	 */
	public function update_order_from_request( $order, $request ): void {
		$ext = ( isset( $request['extensions']['clcf'] ) && is_array( $request['extensions']['clcf'] ) ) ? $request['extensions']['clcf'] : array();
		$raw = ( isset( $ext['fields'] ) && is_array( $ext['fields'] ) ) ? $ext['fields'] : array();

		$group        = $this->repo->get();
		$field_values = Sanitizer::sanitize( $group, $raw );
		$values       = CheckoutConditionData::build( $field_values, $this->request_context( $request ) );

		// Draft/totals updates (e.g. switching payment method fires
		// __experimental_calc_totals) must not surface validation errors —
		// only the real "Place order" submit does.
		$is_draft_update = ! empty( $request['__experimental_calc_totals'] );

		$errors = array_merge(
			Validator::validate( $group, $field_values, $values ),
			FileUploads::validate_tokens( $group, $field_values )
		);
		if ( ! empty( $errors ) && ! $is_draft_update ) {
			throw new RouteException( 'clcf_checkout_fields_invalid', esc_html( (string) $errors[0] ), 400 );
		}

		( new OrderPersistence( $this->repo ) )->save( $order, $group, $field_values, $values );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return array<string,string>
	 */
	private function request_context( $request ): array {
		$ctx = array();
		if ( isset( $request['payment_method'] ) ) {
			$ctx['payment_method'] = (string) $request['payment_method'];
		}
		$billing = $request['billing_address'] ?? array();
		if ( is_array( $billing ) && isset( $billing['country'] ) ) {
			$ctx['billing_country'] = (string) $billing['country'];
		}
		$shipping = $request['shipping_address'] ?? array();
		if ( is_array( $shipping ) && isset( $shipping['country'] ) ) {
			$ctx['shipping_country'] = (string) $shipping['country'];
		}
		return $ctx;
	}
}

/**
 * IntegrationInterface: enqueues the Block-checkout bundle and inlines its config.
 */
final class BlockFieldsIntegration implements IntegrationInterface {

	public function get_name(): string {
		return 'clcf';
	}

	public function initialize(): void {
		$asset_path = CLCF_PATH . 'build/block-checkout.asset.php';
		$asset      = file_exists( $asset_path ) ? require $asset_path : array( 'dependencies' => array(), 'version' => CLCF_VERSION );
		$deps       = array_unique( array_merge( (array) $asset['dependencies'], array( 'wc-blocks-checkout' ) ) );

		wp_register_script( 'clcf-block-checkout', CLCF_URL . 'build/block-checkout.js', $deps, $asset['version'], true );
		wp_set_script_translations( 'clcf-block-checkout', 'corelabs-checkout-fields', CLCF_PATH . 'languages' );

		if ( file_exists( CLCF_PATH . 'build/style-block-checkout.css' ) ) {
			wp_enqueue_style( 'clcf-block-checkout', CLCF_URL . 'build/style-block-checkout.css', array(), $asset['version'] );
		}

		// Static config only — the dynamic context comes from the cart Store API
		// endpoint data (computed per-request, safely after wp_loaded).
		$group = ( new FieldRepository() )->get();
		$data  = array(
			'fields' => array_values( (array) ( $group['fields'] ?? array() ) ),
			'upload' => array(
				'url'    => esc_url_raw( rest_url( 'clcf/v1/upload' ) ),
				'nonce'  => wp_create_nonce( 'clcf_upload' ),
				// Logged-in visitors: REST cookie auth rejects the request before
				// our permission callback unless a wp_rest nonce rides along.
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'accept' => '.' . implode( ',.', FileUploads::allowed_extensions() ),
				'maxMb'  => round( FileUploads::max_bytes() / 1048576, 1 ),
			),
		);
		wp_add_inline_script( 'clcf-block-checkout', 'window.CLCF_BLOCK = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	/** @return string[] */
	public function get_script_handles(): array {
		return array( 'clcf-block-checkout' );
	}

	/** @return string[] */
	public function get_editor_script_handles(): array {
		return array();
	}

	/** @return array<string,mixed> */
	public function get_script_data(): array {
		return array();
	}
}

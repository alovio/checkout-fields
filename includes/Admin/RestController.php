<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Admin;

use CoreLabs\CheckoutFields\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * REST API for the builder: GET/POST the store-wide checkout field group.
 * Capability-gated to shop managers (`manage_woocommerce`).
 */
final class RestController {

	private FieldRepository $repo;

	public function __construct( ?FieldRepository $repo = null ) {
		$this->repo = $repo ?? new FieldRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'clcf/v1',
			'/fields',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_fields' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_fields' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'fields' => array(
							'required' => true,
							'type'     => 'array',
						),
					),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public function get_fields() {
		return rest_ensure_response( $this->repo->get() );
	}

	/** @param \WP_REST_Request $request */
	public function save_fields( $request ) {
		$fields = $request->get_param( 'fields' );
		$group  = array( 'fields' => is_array( $fields ) ? $fields : array() );

		return rest_ensure_response( $this->repo->save( $group ) );
	}
}

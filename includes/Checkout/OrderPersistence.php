<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

use CoreLabs\CheckoutFields\Fields\FieldRepository;
use CoreLabs\CheckoutFields\Logic\ConditionalLogic;

defined( 'ABSPATH' ) || exit;

/**
 * Persists active checkout-field values to the order (HPOS-safe CRUD) and renders
 * them on the admin order screen and order emails. Field values are order-global
 * (not per-line-item), stored as JSON in `_clcf_fields` plus one human-readable
 * meta per field.
 */
final class OrderPersistence {

	private const META = '_clcf_fields';

	private FieldRepository $repo;

	public function __construct( ?FieldRepository $repo = null ) {
		$this->repo = $repo ?? new FieldRepository();
	}

	public function register(): void {
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_classic' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin' ) );
		add_action( 'woocommerce_email_order_meta', array( $this, 'render_email' ), 10, 3 );
	}

	/**
	 * Persist the active field values onto the order.
	 *
	 * @param \WC_Order            $order
	 * @param array<string,mixed>  $group
	 * @param array<string,mixed>  $field_values field id => sanitized value
	 * @param array<string,mixed>  $values       full token map (for visibility)
	 */
	public function save( $order, array $group, array $field_values, array $values ): void {
		$active = ConditionalLogic::active_map( $group, $values );
		$stored = array();

		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$id = (string) ( $f['id'] ?? '' );
			if ( '' === $id || empty( $active[ $id ] ) ) {
				continue;
			}
			$type = (string) ( $f['type'] ?? '' );
			if ( 'heading' === $type ) {
				continue;
			}
			if ( ! array_key_exists( $id, $field_values ) ) {
				continue;
			}
			$val = $field_values[ $id ];
			if ( null === $val || '' === $val ) {
				continue;
			}

			$label = ( '' !== (string) ( $f['label'] ?? '' ) ) ? (string) $f['label'] : $id;
			$files = array();
			if ( 'file' === $type ) {
				// One value can carry several tokens. A token that vanished must
				// not discard the field's other files.
				foreach ( FileUploads::parse_tokens( $val ) as $token ) {
					$resolved = FileUploads::consume( $token );
					if ( null !== $resolved ) {
						$files[] = array( 'name' => $resolved['name'], 'url' => $resolved['url'] );
					}
				}
				if ( array() === $files ) {
					continue; // nothing survived — nothing to attach.
				}
				// Names, not URLs: this string is printed in customer emails.
				$display = implode( ', ', array_map( static fn( $file ) => $file['name'], $files ) );
			} else {
				$display = self::format_value( $f, $val );
			}

			$row = array( 'id' => $id, 'label' => $label, 'display' => $display );
			if ( array() !== $files ) {
				$row['files'] = $files;
			}
			$stored[] = $row;
			$order->add_meta_data( $label, $display, true );
		}

		if ( ! empty( $stored ) ) {
			$order->add_meta_data( self::META, wp_json_encode( $stored ), true );
		}
	}

	/**
	 * @param \WC_Order $order
	 * @param array     $data
	 */
	public function save_classic( $order, $data ): void {
		$group = $this->repo->get();
		// WooCommerce verifies the checkout nonce before this hook fires.
		$raw          = ( isset( $_POST['clcf'] ) && is_array( $_POST['clcf'] ) ) ? wp_unslash( $_POST['clcf'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$field_values = Sanitizer::sanitize( $group, (array) $raw );
		$values       = CheckoutConditionData::build( $field_values, $this->posted_context() );
		$this->save( $order, $group, $field_values, $values );
	}

	/** @return array<string,string> */
	private function posted_context(): array {
		$out = array();
		foreach ( array( 'payment_method', 'billing_country', 'shipping_country' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$out[ $k ] = sanitize_text_field( wp_unslash( (string) $_POST[ $k ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}
		}
		return $out;
	}

	/** @param \WC_Order $order */
	public function render_admin( $order ): void {
		$rows = $this->stored( $order );
		if ( empty( $rows ) ) {
			return;
		}
		echo '<div class="clcf-order-fields"><h3>' . esc_html__( 'Checkout fields', 'corelabs-checkout-fields' ) . '</h3>';
		foreach ( $rows as $r ) {
			$display = (string) $r['display'];
			if ( ! empty( $r['files'] ) && is_array( $r['files'] ) ) {
				$links = array();
				foreach ( $r['files'] as $file ) {
					$links[] = sprintf(
						'<a href="%s" target="_blank" rel="noopener">%s</a>',
						esc_url( (string) ( $file['url'] ?? '' ) ),
						esc_html( (string) ( $file['name'] ?? __( 'View file', 'corelabs-checkout-fields' ) ) )
					);
				}
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each link escaped above.
				printf( '<p><strong>%s:</strong> %s</p>', esc_html( (string) $r['label'] ), implode( ', ', $links ) );
				continue;
			}
			// Orders placed before 1.3.0 stored a bare URL in `display`.
			if ( 0 === strpos( $display, 'http' ) ) {
				printf( '<p><strong>%s:</strong> <a href="%s" target="_blank" rel="noopener">%s</a></p>', esc_html( (string) $r['label'] ), esc_url( $display ), esc_html__( 'View file', 'corelabs-checkout-fields' ) );
			} else {
				printf( '<p><strong>%s:</strong> %s</p>', esc_html( (string) $r['label'] ), esc_html( $display ) );
			}
		}
		echo '</div>';
	}

	/**
	 * @param \WC_Order $order
	 * @param bool      $sent_to_admin
	 * @param bool      $plain_text
	 */
	public function render_email( $order, $sent_to_admin = false, $plain_text = false ): void {
		$rows = $this->stored( $order );
		if ( empty( $rows ) ) {
			return;
		}
		if ( $plain_text ) {
			foreach ( $rows as $r ) {
				echo esc_html( (string) $r['label'] ) . ': ' . esc_html( (string) $r['display'] ) . "\n";
			}
			return;
		}
		echo '<h2>' . esc_html__( 'Checkout fields', 'corelabs-checkout-fields' ) . '</h2><ul>';
		foreach ( $rows as $r ) {
			printf( '<li><strong>%s:</strong> %s</li>', esc_html( (string) $r['label'] ), esc_html( (string) $r['display'] ) );
		}
		echo '</ul>';
	}

	/**
	 * @param \WC_Order $order
	 * @return array<int,array<string,mixed>>
	 */
	private function stored( $order ): array {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return array();
		}
		$raw     = $order->get_meta( self::META, true );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param array<string,mixed> $f
	 * @param mixed               $val
	 */
	private static function format_value( array $f, $val ): string {
		$type = (string) ( $f['type'] ?? '' );
		if ( 'checkbox' === $type ) {
			return __( 'Yes', 'corelabs-checkout-fields' );
		}
		if ( 'number' === $type ) {
			return (string) ( (float) $val );
		}
		return (string) $val;
	}
}

<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

use CoreLabs\CheckoutFields\Fields\FieldRepository;
use CoreLabs\CheckoutFields\Logic\ConditionalLogic;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the checkout fields on the CLASSIC (shortcode) checkout, validates them
 * on submit, and emits the rule + value payloads the front-end runtime uses for
 * live show/hide. All output is escaped; initial visibility is computed
 * server-side so there is no flash for no-JS visitors.
 */
final class ClassicRenderer {

	/** location key => WooCommerce classic-checkout hook. */
	private const REGIONS = array(
		'after_customer_details' => 'woocommerce_checkout_after_customer_details',
		'before_order_notes'     => 'woocommerce_before_order_notes',
		'after_order_notes'      => 'woocommerce_after_order_notes',
		'before_payment'         => 'woocommerce_review_order_before_payment',
	);

	private FieldRepository $repo;

	public function __construct( ?FieldRepository $repo = null ) {
		$this->repo = $repo ?? new FieldRepository();
	}

	public function register(): void {
		foreach ( self::REGIONS as $loc => $hook ) {
			add_action(
				$hook,
				function () use ( $loc ) {
					$this->render_region( $loc );
				}
			);
		}
		add_action( 'woocommerce_after_checkout_form', array( $this, 'render_payload' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_submit' ) );
	}

	private function render_region( string $location ): void {
		$group  = $this->repo->get();
		$fields = array_values(
			array_filter(
				(array) ( $group['fields'] ?? array() ),
				static fn( $f ) => ( $f['location'] ?? 'after_order_notes' ) === $location
			)
		);
		if ( empty( $fields ) ) {
			return;
		}

		$values = CheckoutConditionData::build();
		$active = ConditionalLogic::active_map( $group, $values );

		echo '<div class="clcf-fields clcf-grid clcf-fields--' . esc_attr( $location ) . '">';
		foreach ( $fields as $f ) {
			$this->render_field( $f, ! empty( $active[ $f['id'] ?? '' ] ) );
		}
		echo '</div>';
	}

	/** Emit the full field group + initial token map for the runtime (once). */
	public function render_payload(): void {
		$group = $this->repo->get();
		if ( empty( $group['fields'] ) ) {
			return;
		}
		$values = CheckoutConditionData::build();
		$flags  = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
		echo '<script type="application/json" class="clcf-rules">' . wp_json_encode( $group, $flags ) . '</script>';
		echo '<script type="application/json" class="clcf-values">' . wp_json_encode( $values, $flags ) . '</script>';
	}

	public function validate_submit(): void {
		$group = $this->repo->get();
		if ( empty( $group['fields'] ) ) {
			return;
		}
		// WooCommerce verifies the checkout nonce before this hook fires.
		$raw          = ( isset( $_POST['clcf'] ) && is_array( $_POST['clcf'] ) ) ? wp_unslash( $_POST['clcf'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$field_values = Sanitizer::sanitize( $group, (array) $raw );
		$context      = array();
		foreach ( array( 'payment_method', 'billing_country', 'shipping_country' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$context[ $k ] = sanitize_text_field( wp_unslash( (string) $_POST[ $k ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}
		}
		$values = CheckoutConditionData::build( $field_values, $context );
		foreach ( Validator::validate( $group, $field_values, $values ) as $error ) {
			wc_add_notice( $error, 'error' );
		}
	}

	private function render_field( array $f, bool $active ): void {
		$id    = (string) ( $f['id'] ?? '' );
		$type  = (string) ( $f['type'] ?? '' );
		$label = ( '' !== (string) ( $f['label'] ?? '' ) ) ? (string) $f['label'] : $id;

		$wclass = 'clcf-w-' . ( ( 'half' === ( $f['width'] ?? '' ) ) ? 'half' : 'full' );

		if ( 'heading' === $type ) {
			printf( '<div class="clcf-field clcf-field--heading %s" data-clcf-field="%s"%s>', esc_attr( $wclass ), esc_attr( $id ), $active ? '' : ' hidden' );
			printf( '<h4 class="clcf-heading">%s</h4>', esc_html( $label ) );
			if ( '' !== (string) ( $f['description'] ?? '' ) ) {
				printf( '<p class="clcf-field__desc">%s</p>', esc_html( (string) $f['description'] ) );
			}
			echo '</div>';
			return;
		}

		$fid = 'clcf_field_' . $id;
		printf( '<p class="clcf-field %s" data-clcf-field="%s"%s>', esc_attr( $wclass ), esc_attr( $id ), $active ? '' : ' hidden' );

		$is_group = in_array( $type, array( 'radio' ), true );
		if ( $is_group ) {
			echo '<span class="clcf-field__label">' . esc_html( $label ) . $this->required_mark( $f ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'checkbox' !== $type ) {
			printf( '<label class="clcf-field__label" for="%s">%s%s</label>', esc_attr( $fid ), esc_html( $label ), $this->required_mark( $f ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== (string) ( $f['description'] ?? '' ) ) {
			printf( '<small class="clcf-field__desc">%s</small>', esc_html( (string) $f['description'] ) );
		}
		$this->render_input( $f );
		echo '</p>';
	}

	private function required_mark( array $f ): string {
		return ! empty( $f['required'] ) ? ' <abbr class="required" title="' . esc_attr__( 'required', 'corelabs-checkout-fields' ) . '">*</abbr>' : '';
	}

	private function render_input( array $f ): void {
		$id      = (string) $f['id'];
		$name    = 'clcf[' . $id . ']';
		$fid     = 'clcf_field_' . $id;
		$type    = (string) $f['type'];
		$req     = ! empty( $f['required'] ) ? ' required aria-required="true"' : '';
		$default = (string) ( $f['default'] ?? '' );
		$ph      = ( '' !== (string) ( $f['placeholder'] ?? '' ) ) ? sprintf( ' placeholder="%s"', esc_attr( (string) $f['placeholder'] ) ) : '';

		switch ( $type ) {
			case 'textarea':
				$ml = ! empty( $f['maxLength'] ) ? sprintf( ' maxlength="%d"', (int) $f['maxLength'] ) : '';
				printf( '<textarea id="%s" name="%s" rows="3"%s%s%s>%s</textarea>', esc_attr( $fid ), esc_attr( $name ), $req, $ph, $ml, esc_textarea( $default ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'number':
				$min  = ( '' !== (string) ( $f['min'] ?? '' ) ) ? sprintf( ' min="%s"', esc_attr( (string) $f['min'] ) ) : '';
				$max  = ( '' !== (string) ( $f['max'] ?? '' ) ) ? sprintf( ' max="%s"', esc_attr( (string) $f['max'] ) ) : '';
				$step = ( '' !== (string) ( $f['step'] ?? '' ) ) ? sprintf( ' step="%s"', esc_attr( (string) $f['step'] ) ) : '';
				$val  = ( '' !== $default ) ? sprintf( ' value="%s"', esc_attr( $default ) ) : '';
				printf( '<input type="number" id="%s" name="%s"%s%s%s%s%s%s />', esc_attr( $fid ), esc_attr( $name ), $req, $ph, $min, $max, $step, $val ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'checkbox':
				$chk = ( 'yes' === $default ) ? ' checked' : '';
				printf( '<label class="clcf-checkbox"><input type="checkbox" id="%s" name="%s" value="yes"%s%s /> %s%s</label>', esc_attr( $fid ), esc_attr( $name ), $req, $chk, esc_html( ( '' !== (string) ( $f['label'] ?? '' ) ) ? (string) $f['label'] : $id ), $this->required_mark( $f ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'radio':
				foreach ( (array) ( $f['options'] ?? array() ) as $opt ) {
					$chk = ( (string) $opt === $default ) ? ' checked' : '';
					printf( '<label class="clcf-opt"><input type="radio" name="%s" value="%s"%s%s /> %s</label>', esc_attr( $name ), esc_attr( (string) $opt ), ! empty( $f['required'] ) ? ' required' : '', $chk, esc_html( (string) $opt ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				break;
			case 'select':
				printf( '<select id="%s" name="%s"%s><option value="">%s</option>', esc_attr( $fid ), esc_attr( $name ), $req, esc_html__( 'Choose an option', 'corelabs-checkout-fields' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				foreach ( (array) ( $f['options'] ?? array() ) as $opt ) {
					$sel = ( (string) $opt === $default ) ? ' selected' : '';
					printf( '<option value="%s"%s>%s</option>', esc_attr( (string) $opt ), $sel, esc_html( (string) $opt ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</select>';
				break;
			default: // text
				$ml  = ! empty( $f['maxLength'] ) ? sprintf( ' maxlength="%d"', (int) $f['maxLength'] ) : '';
				$val = ( '' !== $default ) ? sprintf( ' value="%s"', esc_attr( $default ) ) : '';
				printf( '<input type="text" id="%s" name="%s"%s%s%s%s />', esc_attr( $fid ), esc_attr( $name ), $req, $ph, $ml, $val ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

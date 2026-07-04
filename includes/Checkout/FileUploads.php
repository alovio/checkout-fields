<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

use CoreLabs\CheckoutFields\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * File-upload pipeline for `file` fields.
 *
 * The classic checkout submits via an AJAX-serialized form, so raw files cannot
 * ride along — instead the browser uploads the file IMMEDIATELY on selection to
 * `clcf/v1/upload` and the field carries an opaque 32-hex TOKEN in a hidden
 * input (same flow on the Block checkout via extensionData).
 *
 * Hardening: nonce required, per-IP rate limit, extension whitelist + real MIME
 * verification (wp_check_filetype_and_ext), size cap, randomized filename under
 * uploads/clcf/. Tokens map to files via `clcf_upload_<token>` options; a daily
 * cron deletes orphan uploads older than 48h. Attaching to an order consumes
 * the token (the file then belongs to the order).
 */
final class FileUploads {

	public const CRON_HOOK = 'clcf_cleanup_uploads';

	/** @return string[] */
	public static function allowed_extensions(): array {
		return (array) apply_filters( 'clcf_upload_extensions', array( 'jpg', 'jpeg', 'png', 'pdf' ) );
	}

	public static function max_bytes(): int {
		return (int) apply_filters( 'clcf_upload_max_bytes', 5 * 1024 * 1024 );
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'init', array( $this, 'schedule_cleanup' ) );
		add_action( self::CRON_HOOK, array( $this, 'cleanup_orphans' ) );
	}

	public function register_route(): void {
		register_rest_route(
			'clcf/v1',
			'/upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_upload' ),
				'permission_callback' => array( $this, 'can_upload' ),
			)
		);
	}

	/**
	 * Guests upload at checkout, so this is public — but a page-issued nonce is
	 * required and uploads are rate-limited per IP.
	 */
	public function can_upload(): bool {
		$nonce = isset( $_SERVER['HTTP_X_CLCF_NONCE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_CLCF_NONCE'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'clcf_upload' ) ) {
			return false;
		}
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key   = 'clcf_upl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= (int) apply_filters( 'clcf_upload_rate_limit', 20 ) ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Store must actually use file fields for the endpoint to accept anything.
	 */
	private function store_has_file_fields(): bool {
		foreach ( (array) ( ( new FieldRepository() )->get()['fields'] ?? array() ) as $f ) {
			if ( 'file' === ( $f['type'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param \WP_REST_Request $request */
	public function handle_upload( $request ) {
		if ( ! $this->store_has_file_fields() ) {
			return new \WP_Error( 'clcf_no_file_fields', __( 'File uploads are not enabled.', 'corelabs-checkout-fields' ), array( 'status' => 400 ) );
		}

		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
			return new \WP_Error( 'clcf_no_file', __( 'No file received.', 'corelabs-checkout-fields' ), array( 'status' => 400 ) );
		}

		if ( (int) $file['size'] > self::max_bytes() ) {
			return new \WP_Error( 'clcf_too_large', __( 'The file is too large.', 'corelabs-checkout-fields' ), array( 'status' => 413 ) );
		}

		$original = sanitize_file_name( (string) ( $file['name'] ?? 'file' ) );
		$ext      = strtolower( (string) pathinfo( $original, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::allowed_extensions(), true ) ) {
			return new \WP_Error( 'clcf_bad_type', __( 'This file type is not allowed.', 'corelabs-checkout-fields' ), array( 'status' => 415 ) );
		}

		// Verify the real content type matches the extension.
		$check = wp_check_filetype_and_ext( (string) $file['tmp_name'], $original );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || strtolower( (string) $check['ext'] ) !== $ext ) {
			return new \WP_Error( 'clcf_bad_type', __( 'This file type is not allowed.', 'corelabs-checkout-fields' ), array( 'status' => 415 ) );
		}

		$token = bin2hex( random_bytes( 16 ) );

		add_filter( 'upload_dir', array( self::class, 'upload_dir' ) );
		$moved = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'unique_filename_callback' => static function ( $dir, $name, $extension ) use ( $token ) {
					return 'clcf-' . $token . strtolower( (string) $extension );
				},
			)
		);
		remove_filter( 'upload_dir', array( self::class, 'upload_dir' ) );

		if ( ! is_array( $moved ) || empty( $moved['url'] ) || ! empty( $moved['error'] ) ) {
			return new \WP_Error( 'clcf_upload_failed', __( 'Upload failed. Please try again.', 'corelabs-checkout-fields' ), array( 'status' => 500 ) );
		}

		update_option(
			'clcf_upload_' . $token,
			array(
				'url'  => (string) $moved['url'],
				'file' => (string) $moved['file'],
				'name' => $original,
				'time' => time(),
			),
			false
		);

		return rest_ensure_response( array( 'token' => $token, 'name' => $original ) );
	}

	/**
	 * @param array<string,mixed> $dirs
	 * @return array<string,mixed>
	 */
	public static function upload_dir( array $dirs ): array {
		$dirs['subdir'] = '/clcf';
		$dirs['path']   = $dirs['basedir'] . '/clcf';
		$dirs['url']    = $dirs['baseurl'] . '/clcf';
		if ( ! is_dir( $dirs['path'] ) ) {
			wp_mkdir_p( $dirs['path'] );
			// Guard against directory listing.
			@file_put_contents( $dirs['path'] . '/index.html', '' ); // phpcs:ignore
		}
		return $dirs;
	}

	/**
	 * Validate submitted file tokens: format + existence. Returns error strings.
	 *
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $field_values
	 * @return string[]
	 */
	public static function validate_tokens( array $group, array $field_values ): array {
		$errors = array();
		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			if ( 'file' !== ( $f['type'] ?? '' ) ) {
				continue;
			}
			$id  = (string) ( $f['id'] ?? '' );
			$val = (string) ( $field_values[ $id ] ?? '' );
			if ( '' === $val ) {
				continue; // required-ness is handled by Validator.
			}
			$label = ( '' !== (string) ( $f['label'] ?? '' ) ) ? (string) $f['label'] : $id;
			if ( ! preg_match( '/^[a-f0-9]{32}$/', $val ) || ! is_array( get_option( 'clcf_upload_' . $val ) ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( 'The file for “%s” could not be verified — please upload it again.', 'corelabs-checkout-fields' ), $label );
			}
		}
		return $errors;
	}

	/**
	 * Resolve a token for order persistence and CONSUME it (the file now belongs
	 * to the order; the cleanup cron will no longer touch it).
	 *
	 * @return array{url:string,name:string}|null
	 */
	public static function consume( string $token ): ?array {
		$row = get_option( 'clcf_upload_' . $token );
		if ( ! is_array( $row ) || empty( $row['url'] ) ) {
			return null;
		}
		delete_option( 'clcf_upload_' . $token );
		return array( 'url' => (string) $row['url'], 'name' => (string) ( $row['name'] ?? '' ) );
	}

	public function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/** Delete never-attached uploads older than 48h (their token row still exists). */
	public function cleanup_orphans(): void {
		global $wpdb;
		$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'clcf\\_upload\\_%'" ); // phpcs:ignore WordPress.DB
		foreach ( (array) $names as $name ) {
			$row = get_option( $name );
			if ( is_array( $row ) && ! empty( $row['time'] ) && ( time() - (int) $row['time'] ) > 2 * DAY_IN_SECONDS ) {
				if ( ! empty( $row['file'] ) && file_exists( (string) $row['file'] ) ) {
					wp_delete_file( (string) $row['file'] );
				}
				delete_option( $name );
			}
		}
	}
}

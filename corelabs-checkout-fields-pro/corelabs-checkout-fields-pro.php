<?php
/**
 * Plugin Name: CoreLabs Checkout Fields — Pro
 * Plugin URI: https://addons.itahir.com/corelabs-checkout-fields
 * Description: Unlocks Pro features for CoreLabs Checkout Fields: field-driven fees (fixed / per-unit / percentage) and extra field types (file, date).
 * Version: 1.0.0
 * Author: CoreLabs
 * Author URI: https://addons.itahir.com
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;

/**
 * Flip the gate and lift the free-tier limits. The feature implementations live
 * in the (free) core, gated by these filters, so they stay inert until this
 * add-on is active. A license check can layer on `clcf_is_pro` later.
 */
add_filter( 'clcf_is_pro', '__return_true' );

add_filter( 'clcf_field_types', function ( $types ) {
	return array_merge( (array) $types, array( 'file', 'date' ) );
} );

add_filter( 'clcf_price_modes', function () {
	return array( 'fixed', 'per_unit', 'percent' );
} );

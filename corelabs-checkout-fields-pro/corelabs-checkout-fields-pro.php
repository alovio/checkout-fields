<?php
/**
 * Plugin Name: CoreLabs Checkout Fields — Pro
 * Plugin URI: https://addons.itahir.com/corelabs-checkout-fields
 * Description: Unlocks Pro features for CoreLabs Checkout Fields (extra field types, field-driven pricing, and more).
 * Version: 1.0.0
 * Author: CoreLabs
 * Author URI: https://addons.itahir.com
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'clcf_is_pro', '__return_true' );

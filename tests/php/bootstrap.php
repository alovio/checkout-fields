<?php
/**
 * Unit-test bootstrap (Brain Monkey). Defines ABSPATH so class-file guards
 * (`defined('ABSPATH') || exit;`) pass without a full WordPress load.
 */
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

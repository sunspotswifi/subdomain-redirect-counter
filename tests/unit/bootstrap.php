<?php
/**
 * PHPUnit bootstrap file for unit tests.
 *
 * Unit tests use WP_Mock to mock WordPress functions and don't require
 * a WordPress installation or database connection.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

// Load Composer autoload.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Initialize WP_Mock.
WP_Mock::bootstrap();

// Define WordPress constants needed by the plugin.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'SRC_PLUGIN_DIR' ) ) {
	define( 'SRC_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'SRC_VERSION' ) ) {
	define( 'SRC_VERSION', '1.5.0' );
}

// Define common WordPress constants that may be used.
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', '/tmp/wordpress/wp-content' );
}

/**
 * Load plugin classes for unit testing.
 *
 * Note: We don't load the main plugin file as it has side effects.
 * Instead, load individual classes as needed for testing.
 */

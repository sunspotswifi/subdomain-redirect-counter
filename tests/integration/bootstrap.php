<?php
/**
 * PHPUnit bootstrap file for integration tests.
 *
 * Integration tests require a WordPress test environment with a database.
 * Run `bin/install-wp-tests.sh` to set up the test environment.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

// Get the WordPress tests directory.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Check if tests library exists.
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test library at: {$_tests_dir}\n";
	echo "Run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/subdomain-redirect-counter.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Ensure tables are created for testing.
SRC_Database::create_tables();

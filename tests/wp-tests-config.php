<?php
/**
 * WordPress test configuration for local integration runs.
 *
 * Used by tests/integration/bootstrap.php when neither WP_TESTS_DIR nor the
 * install-wp-tests.sh library is present: Composer's wp-phpunit library with
 * this config. The database is local and disposable (`src_test`).
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

// Path to the WordPress install used as ABSPATH during tests.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/wordpress/' );
}

define( 'WP_DEFAULT_THEME', 'twentytwentyfive' );

define( 'DB_NAME', 'src_test' );
define( 'DB_USER', 'agent' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY', 'test-auth-key' );
define( 'SECURE_AUTH_KEY', 'test-secure-auth-key' );
define( 'LOGGED_IN_KEY', 'test-logged-in-key' );
define( 'NONCE_KEY', 'test-nonce-key' );
define( 'AUTH_SALT', 'test-auth-salt' );
define( 'SECURE_AUTH_SALT', 'test-secure-auth-salt' );
define( 'LOGGED_IN_SALT', 'test-logged-in-salt' );
define( 'NONCE_SALT', 'test-nonce-salt' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

define( 'WP_DEBUG', true );

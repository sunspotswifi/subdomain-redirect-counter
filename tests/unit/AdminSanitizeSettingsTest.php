<?php
/**
 * Unit tests for SRC_Admin settings sanitization.
 *
 * Tests input validation and sanitization for plugin settings.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests\Unit
 * @license GPL-2.0-or-later
 *
 * @covers SRC_Admin
 */

use WP_Mock\Tools\TestCase;

/**
 * Class AdminSanitizeSettingsTest
 *
 * @group unit
 * @group admin
 */
class AdminSanitizeSettingsTest extends TestCase {

	/**
	 * The admin instance.
	 *
	 * @var SRC_Admin
	 */
	private $admin;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		// Load required classes.
		if ( ! class_exists( 'SRC_Database' ) ) {
			require_once SRC_PLUGIN_DIR . 'includes/class-src-database.php';
		}
		if ( ! class_exists( 'SRC_Mappings' ) ) {
			require_once SRC_PLUGIN_DIR . 'includes/class-src-mappings.php';
		}
		if ( ! class_exists( 'SRC_Statistics' ) ) {
			require_once SRC_PLUGIN_DIR . 'includes/class-src-statistics.php';
		}
		if ( ! class_exists( 'SRC_Logger' ) ) {
			require_once SRC_PLUGIN_DIR . 'includes/class-src-logger.php';
		}
		if ( ! class_exists( 'SRC_Admin' ) ) {
			require_once SRC_PLUGIN_DIR . 'admin/class-src-admin.php';
		}

		// Get admin instance via reflection.
		$reflection = new ReflectionClass( 'SRC_Admin' );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		// Mock necessary functions for constructor.
		WP_Mock::userFunction( 'add_action' )->andReturn( true );

		$this->admin = SRC_Admin::get_instance();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Reset singleton.
		$reflection = new ReflectionClass( 'SRC_Admin' );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Test enabled checkbox sanitization.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_enabled_checkbox(): void {
		$input = array( 'enabled' => '1' );

		// Mock sanitize functions.
		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$result = $this->admin->sanitize_settings( $input );

		$this->assertTrue( $result['enabled'] );
	}

	/**
	 * Test enabled checkbox with empty value.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_enabled_checkbox_false(): void {
		$input = array();

		// Mock sanitize functions.
		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$result = $this->admin->sanitize_settings( $input );

		$this->assertFalse( $result['enabled'] );
	}

	/**
	 * Test logging_enabled checkbox sanitization.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_logging_enabled_checkbox(): void {
		$input = array( 'logging_enabled' => '1' );

		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$result = $this->admin->sanitize_settings( $input );

		$this->assertTrue( $result['logging_enabled'] );
	}

	/**
	 * Test excluded subdomains string conversion to array.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_excluded_subdomains_string(): void {
		$input = array( 'excluded_subdomains' => 'www, mail, ftp' );

		WP_Mock::userFunction( 'sanitize_key' )->andReturnUsing( function ( $k ) {
			return strtolower( $k );
		} );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$result = $this->admin->sanitize_settings( $input );

		$this->assertIsArray( $result['excluded_subdomains'] );
		$this->assertContains( 'www', $result['excluded_subdomains'] );
		$this->assertContains( 'mail', $result['excluded_subdomains'] );
		$this->assertContains( 'ftp', $result['excluded_subdomains'] );
	}

	/**
	 * Test log retention valid values only.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_log_retention_valid_values(): void {
		$valid_values = array( 0, 7, 14, 30, 60, 90, 180, 365 );

		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		foreach ( $valid_values as $value ) {
			$input  = array( 'log_retention_days' => $value );
			$result = $this->admin->sanitize_settings( $input );

			$this->assertEquals( $value, $result['log_retention_days'] );
		}
	}

	/**
	 * Test log retention invalid value defaults to 0.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_log_retention_invalid_defaults_zero(): void {
		$input = array( 'log_retention_days' => 999 );

		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$result = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 0, $result['log_retention_days'] );
	}

	/**
	 * Test unmapped behavior valid values only.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_unmapped_behavior_valid_values(): void {
		$valid_values = array( 'show', 'redirect' );

		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		foreach ( $valid_values as $value ) {
			$input  = array( 'unmapped_behavior' => $value );
			$result = $this->admin->sanitize_settings( $input );

			$this->assertEquals( $value, $result['unmapped_behavior'] );
		}
	}

	/**
	 * Test unmapped behavior invalid defaults to show.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_unmapped_behavior_invalid_defaults_show(): void {
		$input = array( 'unmapped_behavior' => 'invalid' );

		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$result = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'show', $result['unmapped_behavior'] );
	}

	/**
	 * Test aliased domains removes protocol.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_aliased_domains_removes_protocol(): void {
		$input = array( 'aliased_domains' => 'https://example.org' );

		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$result = $this->admin->sanitize_settings( $input );

		$this->assertContains( 'example.org', $result['aliased_domains'] );
		$this->assertNotContains( 'https://example.org', $result['aliased_domains'] );
	}

	/**
	 * Test aliased domains removes www prefix.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_aliased_domains_removes_www(): void {
		$input = array( 'aliased_domains' => 'www.example.org' );

		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$result = $this->admin->sanitize_settings( $input );

		$this->assertContains( 'example.org', $result['aliased_domains'] );
	}

	/**
	 * Test redirect domains array validation.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_redirect_domains_array(): void {
		$input = array(
			'redirect_domains' => array(
				array(
					'domain'        => 'example.org',
					'target_url'    => 'https://example.com/',
					'redirect_code' => 301,
				),
			),
		);

		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$result = $this->admin->sanitize_settings( $input );

		$this->assertCount( 1, $result['redirect_domains'] );
		$this->assertEquals( 'example.org', $result['redirect_domains'][0]['domain'] );
		$this->assertEquals( 'https://example.com/', $result['redirect_domains'][0]['target_url'] );
		$this->assertEquals( 301, $result['redirect_domains'][0]['redirect_code'] );
	}

	/**
	 * Test redirect domains skips entries with empty domain.
	 *
	 * @covers SRC_Admin::sanitize_settings
	 * @return void
	 */
	public function test_sanitize_redirect_domains_skips_empty_domain(): void {
		$input = array(
			'redirect_domains' => array(
				array(
					'domain'        => '',
					'target_url'    => 'https://example.com/',
					'redirect_code' => 301,
				),
			),
		);

		WP_Mock::userFunction( 'sanitize_key' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'absint' )->andReturnUsing( function ( $v ) {
			return absint( $v );
		} );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$result = $this->admin->sanitize_settings( $input );

		$this->assertCount( 0, $result['redirect_domains'] );
	}
}

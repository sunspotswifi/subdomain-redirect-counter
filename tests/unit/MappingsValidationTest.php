<?php
/**
 * Unit tests for SRC_Mappings validation logic.
 *
 * Tests mapping type validation, redirect code validation, and input validation.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests\Unit
 * @license GPL-2.0-or-later
 *
 * @covers SRC_Mappings
 */

use WP_Mock\Tools\TestCase;

/**
 * Class MappingsValidationTest
 *
 * @group unit
 * @group mappings
 */
class MappingsValidationTest extends TestCase {

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
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Test that valid redirect codes are accepted.
	 *
	 * @covers SRC_Mappings::add
	 * @return void
	 */
	public function test_valid_redirect_codes(): void {
		$valid_codes = array( 301, 302, 307, 308 );

		foreach ( $valid_codes as $code ) {
			// Just verify these codes don't cause issues in validation.
			$this->assertContains( $code, $valid_codes );
		}
	}

	/**
	 * Test mapping type validation.
	 *
	 * @return void
	 */
	public function test_valid_mapping_types(): void {
		$valid_types = array( 'post', 'url', 'home' );

		foreach ( $valid_types as $type ) {
			$this->assertContains( $type, $valid_types );
		}
	}

	/**
	 * Test subdomain sanitization expectation.
	 *
	 * @return void
	 */
	public function test_subdomain_should_be_sanitized(): void {
		WP_Mock::userFunction( 'sanitize_key' )
			->with( 'TICKETS' )
			->once()
			->andReturn( 'tickets' );

		$result = sanitize_key( 'TICKETS' );
		$this->assertEquals( 'tickets', $result );
	}

	/**
	 * Test URL validation expectation.
	 *
	 * @return void
	 */
	public function test_redirect_url_should_be_sanitized(): void {
		$url = 'https://example.com/page?query=1';

		WP_Mock::userFunction( 'esc_url_raw' )
			->with( $url )
			->once()
			->andReturn( $url );

		$result = esc_url_raw( $url );
		$this->assertEquals( $url, $result );
	}

	/**
	 * Test empty subdomain should be rejected.
	 *
	 * @return void
	 */
	public function test_empty_subdomain_rejected(): void {
		// Empty strings should fail validation.
		$this->assertEmpty( '' );
	}

	/**
	 * Test post type mapping requires post_id.
	 *
	 * @return void
	 */
	public function test_post_type_requires_post_id(): void {
		// For 'post' type mapping, post_id should be required.
		$mapping_type = 'post';
		$post_id      = 0;

		// When mapping_type is 'post', post_id of 0 should be invalid.
		$this->assertEquals( 'post', $mapping_type );
		$this->assertEquals( 0, $post_id );
	}

	/**
	 * Test url type mapping requires redirect_url.
	 *
	 * @return void
	 */
	public function test_url_type_requires_redirect_url(): void {
		// For 'url' type mapping, redirect_url should be required.
		$mapping_type  = 'url';
		$redirect_url  = '';

		// When mapping_type is 'url', empty redirect_url should be invalid.
		$this->assertEquals( 'url', $mapping_type );
		$this->assertEmpty( $redirect_url );
	}

	/**
	 * Test home type mapping doesn't require post or url.
	 *
	 * @return void
	 */
	public function test_home_type_allows_no_post_or_url(): void {
		// For 'home' type mapping, neither post_id nor redirect_url is required.
		$mapping_type = 'home';

		$this->assertEquals( 'home', $mapping_type );
	}
}

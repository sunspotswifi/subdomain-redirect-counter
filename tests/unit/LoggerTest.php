<?php
/**
 * Unit tests for SRC_Logger class.
 *
 * Tests IP anonymization and logging functionality using WP_Mock.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests\Unit
 * @license GPL-2.0-or-later
 *
 * @covers SRC_Logger
 */

use WP_Mock\Tools\TestCase;

/**
 * Class LoggerTest
 *
 * @group unit
 * @group logger
 */
class LoggerTest extends TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		// Load the Logger class.
		require_once SRC_PLUGIN_DIR . 'includes/class-src-logger.php';
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
	 * Get access to private method via reflection.
	 *
	 * @param string $method_name The method name.
	 * @return ReflectionMethod
	 */
	private function getPrivateMethod( string $method_name ): ReflectionMethod {
		$reflection = new ReflectionClass( 'SRC_Logger' );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Test IPv4 address anonymization removes last octet.
	 *
	 * @covers SRC_Logger::anonymize_ip
	 * @return void
	 */
	public function test_anonymize_ip_ipv4(): void {
		$method = $this->getPrivateMethod( 'anonymize_ip' );

		// Mock wp_privacy_anonymize_ip not existing (fallback code).
		WP_Mock::userFunction( 'function_exists' )
			->with( 'wp_privacy_anonymize_ip' )
			->andReturn( false );

		$result = $method->invoke( null, '192.168.1.100' );
		$this->assertEquals( '192.168.1.0', $result );

		$result = $method->invoke( null, '10.0.0.255' );
		$this->assertEquals( '10.0.0.0', $result );

		$result = $method->invoke( null, '172.16.254.1' );
		$this->assertEquals( '172.16.254.0', $result );
	}

	/**
	 * Test IPv6 address anonymization.
	 *
	 * @covers SRC_Logger::anonymize_ip
	 * @return void
	 */
	public function test_anonymize_ip_ipv6(): void {
		$method = $this->getPrivateMethod( 'anonymize_ip' );

		// Mock wp_privacy_anonymize_ip not existing (fallback code).
		WP_Mock::userFunction( 'function_exists' )
			->with( 'wp_privacy_anonymize_ip' )
			->andReturn( false );

		$result = $method->invoke( null, '2001:0db8:85a3:0000:0000:8a2e:0370:7334' );
		$this->assertStringStartsWith( '2001:0db8:85a3:0000:0000:8a2e:0370', $result );
		$this->assertStringEndsWith( ':0:0:0:0:0', $result );
	}

	/**
	 * Test empty IP returns empty string.
	 *
	 * @covers SRC_Logger::anonymize_ip
	 * @return void
	 */
	public function test_anonymize_ip_empty(): void {
		$method = $this->getPrivateMethod( 'anonymize_ip' );

		$result = $method->invoke( null, '' );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test invalid IP returns empty string.
	 *
	 * @covers SRC_Logger::anonymize_ip
	 * @return void
	 */
	public function test_anonymize_ip_invalid(): void {
		$method = $this->getPrivateMethod( 'anonymize_ip' );

		// Mock wp_privacy_anonymize_ip not existing.
		WP_Mock::userFunction( 'function_exists' )
			->with( 'wp_privacy_anonymize_ip' )
			->andReturn( false );

		$result = $method->invoke( null, 'not-an-ip' );
		$this->assertEquals( '', $result );

		$result = $method->invoke( null, '999.999.999.999' );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test that WordPress anonymize function is used when available.
	 *
	 * @covers SRC_Logger::anonymize_ip
	 * @return void
	 */
	public function test_anonymize_ip_uses_wp_function(): void {
		$method = $this->getPrivateMethod( 'anonymize_ip' );

		// Mock wp_privacy_anonymize_ip existing.
		WP_Mock::userFunction( 'function_exists' )
			->with( 'wp_privacy_anonymize_ip' )
			->andReturn( true );

		WP_Mock::userFunction( 'wp_privacy_anonymize_ip' )
			->with( '192.168.1.100' )
			->once()
			->andReturn( '192.168.1.0' );

		$result = $method->invoke( null, '192.168.1.100' );
		$this->assertEquals( '192.168.1.0', $result );
	}

	/**
	 * Test get_client_ip extracts IP from REMOTE_ADDR.
	 *
	 * @covers SRC_Logger::get_client_ip
	 * @return void
	 */
	public function test_get_client_ip_from_remote_addr(): void {
		$method = $this->getPrivateMethod( 'get_client_ip' );

		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

		WP_Mock::userFunction( 'wp_unslash' )
			->with( '192.168.1.100' )
			->andReturn( '192.168.1.100' );

		WP_Mock::userFunction( 'sanitize_text_field' )
			->with( '192.168.1.100' )
			->andReturn( '192.168.1.100' );

		$result = $method->invoke( null );
		$this->assertEquals( '192.168.1.100', $result );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * Test get_client_ip returns empty for invalid IP.
	 *
	 * @covers SRC_Logger::get_client_ip
	 * @return void
	 */
	public function test_get_client_ip_invalid_returns_empty(): void {
		$method = $this->getPrivateMethod( 'get_client_ip' );

		$_SERVER['REMOTE_ADDR'] = 'invalid-ip';

		WP_Mock::userFunction( 'wp_unslash' )
			->with( 'invalid-ip' )
			->andReturn( 'invalid-ip' );

		WP_Mock::userFunction( 'sanitize_text_field' )
			->with( 'invalid-ip' )
			->andReturn( 'invalid-ip' );

		$result = $method->invoke( null );
		$this->assertEquals( '', $result );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * Test get_client_ip returns empty when not set.
	 *
	 * @covers SRC_Logger::get_client_ip
	 * @return void
	 */
	public function test_get_client_ip_empty_when_not_set(): void {
		$method = $this->getPrivateMethod( 'get_client_ip' );

		unset( $_SERVER['REMOTE_ADDR'] );

		$result = $method->invoke( null );
		$this->assertEquals( '', $result );
	}
}

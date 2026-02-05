<?php
/**
 * Unit tests for SRC_Interceptor subdomain detection.
 *
 * Tests subdomain parsing, exclusion logic, and aliased domains using WP_Mock.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests\Unit
 * @license GPL-2.0-or-later
 *
 * @covers SRC_Interceptor
 */

use WP_Mock\Tools\TestCase;

/**
 * Class InterceptorSubdomainDetectionTest
 *
 * @group unit
 * @group interceptor
 */
class InterceptorSubdomainDetectionTest extends TestCase {

	/**
	 * Backup of $_SERVER.
	 *
	 * @var array
	 */
	private array $server_backup;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->server_backup = $_SERVER;

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
		if ( ! class_exists( 'SRC_Interceptor' ) ) {
			require_once SRC_PLUGIN_DIR . 'includes/class-src-interceptor.php';
		}
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_SERVER = $this->server_backup;

		// Reset singleton instance.
		$reflection = new ReflectionClass( 'SRC_Interceptor' );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Get access to private method via reflection.
	 *
	 * @param object $object      The object instance.
	 * @param string $method_name The method name.
	 * @return ReflectionMethod
	 */
	private function getPrivateMethod( object $object, string $method_name ): ReflectionMethod {
		$reflection = new ReflectionClass( $object );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Set up common mocks for interceptor initialization.
	 *
	 * @param string $host              The HTTP_HOST value.
	 * @param string $siteurl           The site URL.
	 * @param array  $settings          The plugin settings.
	 * @param bool   $enabled           Whether plugin is enabled.
	 * @return void
	 */
	private function setupMocksForHost(
		string $host,
		string $siteurl = 'https://example.com',
		array $settings = array(),
		bool $enabled = true
	): void {
		$_SERVER['HTTP_HOST'] = $host;

		$default_settings = array(
			'enabled'             => $enabled,
			'excluded_subdomains' => array( 'www', 'mail', 'ftp', 'cpanel', 'webmail' ),
			'aliased_domains'     => array(),
			'logging_enabled'     => false,
		);
		$settings         = array_merge( $default_settings, $settings );

		WP_Mock::userFunction( 'sanitize_text_field' )
			->andReturnUsing(
				function ( $str ) {
					return $str;
				}
			);

		WP_Mock::userFunction( 'wp_unslash' )
			->andReturnUsing(
				function ( $str ) {
					return $str;
				}
			);

		WP_Mock::userFunction( 'get_option' )
			->with( 'siteurl' )
			->andReturn( $siteurl );

		WP_Mock::userFunction( 'get_option' )
			->with( 'src_settings', \WP_Mock\Functions::type( 'array' ) )
			->andReturn( $settings );

		WP_Mock::userFunction( 'wp_parse_url' )
			->andReturnUsing(
				function ( $url ) {
					return parse_url( $url );
				}
			);
	}

	/**
	 * Test valid subdomain is detected correctly.
	 *
	 * @covers SRC_Interceptor::detect_subdomain
	 * @return void
	 */
	public function test_detect_subdomain_valid(): void {
		$this->setupMocksForHost( 'tickets.example.com' );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertEquals( 'tickets', $subdomain );
	}

	/**
	 * Test excluded subdomain returns null.
	 *
	 * @covers SRC_Interceptor::detect_subdomain
	 * @covers SRC_Interceptor::is_excluded
	 * @return void
	 */
	public function test_detect_subdomain_excluded(): void {
		$this->setupMocksForHost( 'www.example.com' );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertNull( $subdomain );
	}

	/**
	 * Test mail subdomain is excluded by default.
	 *
	 * @covers SRC_Interceptor::is_excluded
	 * @return void
	 */
	public function test_detect_subdomain_mail_excluded(): void {
		$this->setupMocksForHost( 'mail.example.com' );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertNull( $subdomain );
	}

	/**
	 * Test base domain without subdomain returns null.
	 *
	 * @covers SRC_Interceptor::detect_subdomain
	 * @return void
	 */
	public function test_detect_subdomain_no_subdomain(): void {
		$this->setupMocksForHost( 'example.com' );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertNull( $subdomain );
	}

	/**
	 * Test port is stripped from host.
	 *
	 * @covers SRC_Interceptor::detect_subdomain
	 * @return void
	 */
	public function test_detect_subdomain_with_port(): void {
		$this->setupMocksForHost( 'tickets.example.com:8080' );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertEquals( 'tickets', $subdomain );
	}

	/**
	 * Test subdomain detection on aliased domain.
	 *
	 * @covers SRC_Interceptor::detect_subdomain
	 * @covers SRC_Interceptor::get_valid_domains
	 * @return void
	 */
	public function test_detect_subdomain_aliased_domain(): void {
		$settings = array(
			'aliased_domains' => array( 'example.org', 'example.net' ),
		);
		$this->setupMocksForHost( 'tickets.example.org', 'https://example.com', $settings );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertEquals( 'tickets', $subdomain );
	}

	/**
	 * Test custom exclusion list is respected.
	 *
	 * @covers SRC_Interceptor::is_excluded
	 * @return void
	 */
	public function test_is_excluded_custom_list(): void {
		$settings = array(
			'excluded_subdomains' => array( 'www', 'staging', 'dev' ),
		);
		$this->setupMocksForHost( 'staging.example.com', 'https://example.com', $settings );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertNull( $subdomain );
	}

	/**
	 * Test subdomain with numbers.
	 *
	 * @covers SRC_Interceptor::detect_subdomain
	 * @return void
	 */
	public function test_detect_subdomain_with_numbers(): void {
		$this->setupMocksForHost( 'app2.example.com' );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertEquals( 'app2', $subdomain );
	}

	/**
	 * Test subdomain with hyphens.
	 *
	 * @covers SRC_Interceptor::detect_subdomain
	 * @return void
	 */
	public function test_detect_subdomain_with_hyphens(): void {
		$this->setupMocksForHost( 'my-app.example.com' );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertEquals( 'my-app', $subdomain );
	}

	/**
	 * Test empty HTTP_HOST returns null.
	 *
	 * @covers SRC_Interceptor::detect_subdomain
	 * @return void
	 */
	public function test_detect_subdomain_empty_host(): void {
		unset( $_SERVER['HTTP_HOST'] );

		WP_Mock::userFunction( 'get_option' )
			->andReturn( array() );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertNull( $subdomain );
	}

	/**
	 * Test subdomain is lowercased.
	 *
	 * @covers SRC_Interceptor::detect_subdomain
	 * @return void
	 */
	public function test_detect_subdomain_is_lowercased(): void {
		$this->setupMocksForHost( 'TICKETS.example.com' );

		$interceptor = SRC_Interceptor::get_instance();
		$subdomain   = $interceptor->get_subdomain();

		$this->assertEquals( 'tickets', $subdomain );
	}
}

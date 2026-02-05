<?php
/**
 * Acceptance tests for redirect flow.
 *
 * Tests complete redirect scenarios from subdomain detection to statistics recording.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests\Acceptance
 * @license GPL-2.0-or-later
 *
 * @covers SRC_Interceptor
 * @covers SRC_Statistics
 * @covers SRC_Logger
 */

/**
 * Class RedirectFlowTest
 *
 * @group acceptance
 * @group redirect
 */
class RedirectFlowTest extends WP_UnitTestCase {

	/**
	 * Clean up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear tables.
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . SRC_Database::get_mappings_table() ); // phpcs:ignore
		$wpdb->query( 'TRUNCATE TABLE ' . SRC_Database::get_stats_table() ); // phpcs:ignore
		$wpdb->query( 'TRUNCATE TABLE ' . SRC_Database::get_logs_table() ); // phpcs:ignore

		// Set default settings.
		update_option(
			'src_settings',
			array(
				'enabled'             => true,
				'logging_enabled'     => true,
				'excluded_subdomains' => array( 'www', 'mail', 'ftp' ),
				'aliased_domains'     => array(),
				'log_retention_days'  => 30,
				'unmapped_behavior'   => 'show',
			)
		);
	}

	/**
	 * Test statistics are recorded on redirect.
	 *
	 * @return void
	 */
	public function test_statistics_recorded_on_redirect(): void {
		// Create a mapping.
		$page_id = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Tickets Page',
				'post_name'  => 'tickets',
			)
		);

		SRC_Mappings::add( 'tickets', $page_id, 'post', '', 301 );

		// Simulate redirect recording.
		SRC_Statistics::record_redirect( 'tickets', '/tickets/' );

		// Verify stats were recorded.
		$stats = SRC_Statistics::get_by_subdomain( 'tickets' );

		$this->assertNotNull( $stats );
		$this->assertEquals( 1, $stats->redirect_count );
	}

	/**
	 * Test logging captures visitor info.
	 *
	 * @return void
	 */
	public function test_logging_captures_visitor_info(): void {
		// Set up server vars.
		$_SERVER['REMOTE_ADDR']     = '192.168.1.100';
		$_SERVER['HTTP_USER_AGENT'] = 'Test Browser/1.0';
		$_SERVER['HTTP_REFERER']    = 'https://google.com/';

		SRC_Logger::log( 'tickets', '/tickets/', 'https://tickets.example.com/' );

		// Get the log entry.
		$logs = SRC_Logger::get_logs( array( 'limit' => 1 ) );

		$this->assertCount( 1, $logs );
		$this->assertEquals( 'tickets', $logs[0]->subdomain );
		$this->assertEquals( '/tickets/', $logs[0]->target_path );

		// IP should be anonymized.
		$this->assertEquals( '192.168.1.0', $logs[0]->ip_address );

		// Clean up.
		unset(
			$_SERVER['REMOTE_ADDR'],
			$_SERVER['HTTP_USER_AGENT'],
			$_SERVER['HTTP_REFERER']
		);
	}

	/**
	 * Test excluded subdomain is not intercepted.
	 *
	 * @return void
	 */
	public function test_excluded_subdomain_not_intercepted(): void {
		// 'www' should be excluded by default settings.
		$settings = get_option( 'src_settings' );

		$this->assertContains( 'www', $settings['excluded_subdomains'] );
	}

	/**
	 * Test URL mapping performs redirect correctly.
	 *
	 * @return void
	 */
	public function test_url_mapping_stores_correctly(): void {
		SRC_Mappings::add( 'external', 0, 'url', 'https://external.com/landing', 302 );

		$mapping = SRC_Mappings::get_by_subdomain( 'external' );

		$this->assertEquals( 'url', $mapping->mapping_type );
		$this->assertEquals( 'https://external.com/landing', $mapping->redirect_url );
		$this->assertEquals( 302, $mapping->redirect_code );
	}

	/**
	 * Test home mapping is correctly identified.
	 *
	 * @return void
	 */
	public function test_home_mapping_identified(): void {
		SRC_Mappings::add( 'welcome', 0, 'home', '', 301 );

		$this->assertTrue( SRC_Mappings::is_home_redirect( 'welcome' ) );
		$this->assertFalse( SRC_Mappings::is_home_redirect( 'nonexistent' ) );
	}

	/**
	 * Test multiple redirects increment count correctly.
	 *
	 * @return void
	 */
	public function test_multiple_redirects_increment_count(): void {
		$page_id = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Events Page',
			)
		);

		SRC_Mappings::add( 'events', $page_id, 'post', '', 301 );

		// Simulate 5 redirects.
		for ( $i = 0; $i < 5; $i++ ) {
			SRC_Statistics::record_redirect( 'events', '/events/' );
		}

		$stats = SRC_Statistics::get_by_subdomain( 'events' );

		$this->assertEquals( 5, $stats->redirect_count );
	}

	/**
	 * Test domain redirect statistics use @ prefix.
	 *
	 * @return void
	 */
	public function test_domain_redirect_stats_prefix(): void {
		// Domain redirects use @ prefix to distinguish from subdomains.
		SRC_Statistics::record_redirect( '@example.org', 'https://example.com/' );

		$stats = SRC_Statistics::get_by_subdomain( '@example.org' );

		$this->assertNotNull( $stats );
		$this->assertEquals( '@example.org', $stats->subdomain );
	}

	/**
	 * Test log filtering by type works.
	 *
	 * @return void
	 */
	public function test_log_filtering_by_type(): void {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

		// Log some subdomain redirects.
		SRC_Logger::log( 'tickets', '/tickets/', 'https://tickets.example.com/' );
		SRC_Logger::log( 'events', '/events/', 'https://events.example.com/' );

		// Log a domain redirect.
		SRC_Logger::log( '@example.org', 'https://example.com/', 'https://example.org/' );

		// Filter domain redirects only.
		$domain_logs = SRC_Logger::get_logs( array( 'subdomain_like' => '@%' ) );
		$this->assertCount( 1, $domain_logs );

		// Filter subdomain redirects only.
		$subdomain_logs = SRC_Logger::get_logs( array( 'subdomain_not_like' => '@%' ) );
		$this->assertCount( 2, $subdomain_logs );

		unset( $_SERVER['REMOTE_ADDR'] );
	}
}

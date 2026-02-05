<?php
/**
 * Integration tests for SRC_Statistics class.
 *
 * Tests redirect statistics recording and retrieval.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests\Integration
 * @license GPL-2.0-or-later
 *
 * @covers SRC_Statistics
 */

/**
 * Class StatisticsTest
 *
 * @group integration
 * @group statistics
 */
class StatisticsTest extends WP_UnitTestCase {

	/**
	 * Clean up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear statistics table.
		global $wpdb;
		$table = SRC_Database::get_stats_table();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore
	}

	/**
	 * Test recording a redirect creates new record.
	 *
	 * @covers SRC_Statistics::record_redirect
	 * @return void
	 */
	public function test_record_redirect_creates_new(): void {
		$result = SRC_Statistics::record_redirect( 'tickets', '/tickets/' );

		$this->assertTrue( $result );

		$stats = SRC_Statistics::get_by_subdomain( 'tickets' );

		$this->assertNotNull( $stats );
		$this->assertEquals( 'tickets', $stats->subdomain );
		$this->assertEquals( '/tickets/', $stats->target_path );
		$this->assertEquals( 1, $stats->redirect_count );
	}

	/**
	 * Test recording multiple redirects increments count.
	 *
	 * @covers SRC_Statistics::record_redirect
	 * @return void
	 */
	public function test_record_redirect_increments_existing(): void {
		SRC_Statistics::record_redirect( 'counter', '/page/' );
		SRC_Statistics::record_redirect( 'counter', '/page/' );
		SRC_Statistics::record_redirect( 'counter', '/page/' );

		$stats = SRC_Statistics::get_by_subdomain( 'counter' );

		$this->assertEquals( 3, $stats->redirect_count );
	}

	/**
	 * Test recording updates last_redirect timestamp.
	 *
	 * @covers SRC_Statistics::record_redirect
	 * @return void
	 */
	public function test_record_redirect_updates_last_redirect(): void {
		SRC_Statistics::record_redirect( 'timestamp', '/page/' );

		$stats = SRC_Statistics::get_by_subdomain( 'timestamp' );

		$this->assertNotNull( $stats->last_redirect );

		$last_redirect = strtotime( $stats->last_redirect );
		$now           = time();

		// Should be within the last minute.
		$this->assertLessThan( 60, $now - $last_redirect );
	}

	/**
	 * Test get_all default ordering.
	 *
	 * @covers SRC_Statistics::get_all
	 * @return void
	 */
	public function test_get_all_default_order(): void {
		SRC_Statistics::record_redirect( 'low', '/page/' );
		SRC_Statistics::record_redirect( 'high', '/page/' );
		SRC_Statistics::record_redirect( 'high', '/page/' );
		SRC_Statistics::record_redirect( 'high', '/page/' );
		SRC_Statistics::record_redirect( 'medium', '/page/' );
		SRC_Statistics::record_redirect( 'medium', '/page/' );

		$all = SRC_Statistics::get_all();

		// Default order should be by redirect_count DESC.
		$this->assertEquals( 'high', $all[0]->subdomain );
		$this->assertEquals( 'medium', $all[1]->subdomain );
		$this->assertEquals( 'low', $all[2]->subdomain );
	}

	/**
	 * Test get_total_count.
	 *
	 * @covers SRC_Statistics::get_total_count
	 * @return void
	 */
	public function test_get_total_count(): void {
		SRC_Statistics::record_redirect( 'sub1', '/page/' );
		SRC_Statistics::record_redirect( 'sub2', '/page/' );
		SRC_Statistics::record_redirect( 'sub3', '/page/' );

		$this->assertEquals( 3, SRC_Statistics::get_total_count() );
	}

	/**
	 * Test get_total_redirects sums all counts.
	 *
	 * @covers SRC_Statistics::get_total_redirects
	 * @return void
	 */
	public function test_get_total_redirects(): void {
		SRC_Statistics::record_redirect( 'sub1', '/page/' );
		SRC_Statistics::record_redirect( 'sub1', '/page/' );
		SRC_Statistics::record_redirect( 'sub2', '/page/' );
		SRC_Statistics::record_redirect( 'sub2', '/page/' );
		SRC_Statistics::record_redirect( 'sub2', '/page/' );

		$this->assertEquals( 5, SRC_Statistics::get_total_redirects() );
	}

	/**
	 * Test get_top_subdomains.
	 *
	 * @covers SRC_Statistics::get_top_subdomains
	 * @return void
	 */
	public function test_get_top_subdomains_limit(): void {
		for ( $i = 1; $i <= 10; $i++ ) {
			for ( $j = 0; $j < $i; $j++ ) {
				SRC_Statistics::record_redirect( "sub{$i}", '/page/' );
			}
		}

		$top5 = SRC_Statistics::get_top_subdomains( 5 );

		$this->assertCount( 5, $top5 );
		$this->assertEquals( 'sub10', $top5[0]->subdomain );
		$this->assertEquals( 10, $top5[0]->redirect_count );
	}

	/**
	 * Test get_recent orders by last_redirect.
	 *
	 * @covers SRC_Statistics::get_recent
	 * @return void
	 */
	public function test_get_recent_by_last_redirect(): void {
		SRC_Statistics::record_redirect( 'first', '/page/' );
		sleep( 1 );
		SRC_Statistics::record_redirect( 'second', '/page/' );
		sleep( 1 );
		SRC_Statistics::record_redirect( 'third', '/page/' );

		$recent = SRC_Statistics::get_recent( 3 );

		$this->assertEquals( 'third', $recent[0]->subdomain );
		$this->assertEquals( 'second', $recent[1]->subdomain );
		$this->assertEquals( 'first', $recent[2]->subdomain );
	}

	/**
	 * Test reset_subdomain removes single record.
	 *
	 * @covers SRC_Statistics::reset_subdomain
	 * @return void
	 */
	public function test_reset_subdomain(): void {
		SRC_Statistics::record_redirect( 'keep', '/page/' );
		SRC_Statistics::record_redirect( 'delete', '/page/' );

		SRC_Statistics::reset_subdomain( 'delete' );

		$this->assertNotNull( SRC_Statistics::get_by_subdomain( 'keep' ) );
		$this->assertNull( SRC_Statistics::get_by_subdomain( 'delete' ) );
	}

	/**
	 * Test reset_all truncates table.
	 *
	 * @covers SRC_Statistics::reset_all
	 * @return void
	 */
	public function test_reset_all(): void {
		SRC_Statistics::record_redirect( 'sub1', '/page/' );
		SRC_Statistics::record_redirect( 'sub2', '/page/' );
		SRC_Statistics::record_redirect( 'sub3', '/page/' );

		$result = SRC_Statistics::reset_all();

		$this->assertTrue( $result );
		$this->assertEquals( 0, SRC_Statistics::get_total_count() );
	}

	/**
	 * Test get_summary returns correct data.
	 *
	 * @covers SRC_Statistics::get_summary
	 * @return void
	 */
	public function test_get_summary(): void {
		SRC_Statistics::record_redirect( 'sub1', '/page/' );
		SRC_Statistics::record_redirect( 'sub1', '/page/' );
		SRC_Statistics::record_redirect( 'sub2', '/page/' );

		$summary = SRC_Statistics::get_summary();

		$this->assertEquals( 2, $summary['total_subdomains'] );
		$this->assertEquals( 3, $summary['total_redirects'] );
		$this->assertNotNull( $summary['last_redirect_time'] );
	}
}

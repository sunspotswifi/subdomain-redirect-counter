<?php
/**
 * Integration tests for SRC_Database class.
 *
 * Tests database table creation, schema validation, and table operations.
 *
 * @package Subdomain_Redirect_Counter
 * @subpackage Tests\Integration
 * @license GPL-2.0-or-later
 *
 * @covers SRC_Database
 */

/**
 * Class DatabaseTest
 *
 * @group integration
 * @group database
 */
class DatabaseTest extends WP_UnitTestCase {

	/**
	 * Test that create_tables creates all three tables.
	 *
	 * @covers SRC_Database::create_tables
	 * @return void
	 */
	public function test_create_tables_creates_all_three(): void {
		global $wpdb;

		// Tables should already exist from bootstrap.
		$stats_table    = SRC_Database::get_stats_table();
		$logs_table     = SRC_Database::get_logs_table();
		$mappings_table = SRC_Database::get_mappings_table();

		// Check tables exist.
		$tables = $wpdb->get_col( 'SHOW TABLES' );

		$this->assertContains( $stats_table, $tables, 'Statistics table should exist' );
		$this->assertContains( $logs_table, $tables, 'Logs table should exist' );
		$this->assertContains( $mappings_table, $tables, 'Mappings table should exist' );
	}

	/**
	 * Test that tables have correct column structure.
	 *
	 * @covers SRC_Database::create_tables
	 * @return void
	 */
	public function test_tables_have_correct_columns(): void {
		global $wpdb;

		// Check statistics table columns.
		$stats_table   = SRC_Database::get_stats_table();
		$stats_columns = $wpdb->get_col( "DESCRIBE {$stats_table}", 0 );

		$this->assertContains( 'id', $stats_columns );
		$this->assertContains( 'subdomain', $stats_columns );
		$this->assertContains( 'target_path', $stats_columns );
		$this->assertContains( 'redirect_count', $stats_columns );
		$this->assertContains( 'last_redirect', $stats_columns );
		$this->assertContains( 'created_at', $stats_columns );

		// Check logs table columns.
		$logs_table   = SRC_Database::get_logs_table();
		$logs_columns = $wpdb->get_col( "DESCRIBE {$logs_table}", 0 );

		$this->assertContains( 'id', $logs_columns );
		$this->assertContains( 'subdomain', $logs_columns );
		$this->assertContains( 'target_path', $logs_columns );
		$this->assertContains( 'source_url', $logs_columns );
		$this->assertContains( 'user_agent', $logs_columns );
		$this->assertContains( 'ip_address', $logs_columns );
		$this->assertContains( 'referer', $logs_columns );
		$this->assertContains( 'created_at', $logs_columns );

		// Check mappings table columns.
		$mappings_table   = SRC_Database::get_mappings_table();
		$mappings_columns = $wpdb->get_col( "DESCRIBE {$mappings_table}", 0 );

		$this->assertContains( 'id', $mappings_columns );
		$this->assertContains( 'subdomain', $mappings_columns );
		$this->assertContains( 'mapping_type', $mappings_columns );
		$this->assertContains( 'post_id', $mappings_columns );
		$this->assertContains( 'redirect_url', $mappings_columns );
		$this->assertContains( 'redirect_code', $mappings_columns );
		$this->assertContains( 'is_active', $mappings_columns );
	}

	/**
	 * Test table name uses correct prefix.
	 *
	 * @covers SRC_Database::get_stats_table
	 * @covers SRC_Database::get_logs_table
	 * @covers SRC_Database::get_mappings_table
	 * @return void
	 */
	public function test_table_prefix_is_correct(): void {
		global $wpdb;

		$stats_table    = SRC_Database::get_stats_table();
		$logs_table     = SRC_Database::get_logs_table();
		$mappings_table = SRC_Database::get_mappings_table();

		$this->assertStringStartsWith( $wpdb->prefix, $stats_table );
		$this->assertStringStartsWith( $wpdb->prefix, $logs_table );
		$this->assertStringStartsWith( $wpdb->prefix, $mappings_table );

		$this->assertStringEndsWith( 'src_statistics', $stats_table );
		$this->assertStringEndsWith( 'src_logs', $logs_table );
		$this->assertStringEndsWith( 'src_mappings', $mappings_table );
	}

	/**
	 * Test statistics table has unique index on subdomain.
	 *
	 * @covers SRC_Database::create_tables
	 * @return void
	 */
	public function test_statistics_table_has_unique_subdomain(): void {
		global $wpdb;

		$table = SRC_Database::get_stats_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table}" );

		$has_unique = false;
		foreach ( $indexes as $index ) {
			if ( 'subdomain' === $index->Column_name && '0' === $index->Non_unique ) {
				$has_unique = true;
				break;
			}
		}

		$this->assertTrue( $has_unique, 'Statistics table should have unique index on subdomain' );
	}

	/**
	 * Test mappings table has unique index on subdomain.
	 *
	 * @covers SRC_Database::create_tables
	 * @return void
	 */
	public function test_mappings_table_has_unique_subdomain(): void {
		global $wpdb;

		$table = SRC_Database::get_mappings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table}" );

		$has_unique = false;
		foreach ( $indexes as $index ) {
			if ( 'subdomain' === $index->Column_name && '0' === $index->Non_unique ) {
				$has_unique = true;
				break;
			}
		}

		$this->assertTrue( $has_unique, 'Mappings table should have unique index on subdomain' );
	}
}

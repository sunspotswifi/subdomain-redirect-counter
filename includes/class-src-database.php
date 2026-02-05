<?php
/**
 * Database handler class.
 *
 * @package Subdomain_Redirect_Counter
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database operations for the plugin.
 *
 * @since 1.0.0
 */
class SRC_Database {

	/**
	 * Database version for migrations.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.3.0';

	/**
	 * Get the statistics table name.
	 *
	 * @return string
	 */
	public static function get_stats_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'src_statistics';
	}

	/**
	 * Get the logs table name.
	 *
	 * @return string
	 */
	public static function get_logs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'src_logs';
	}

	/**
	 * Get the mappings table name.
	 *
	 * @return string
	 */
	public static function get_mappings_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'src_mappings';
	}

	/**
	 * Create database tables.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate  = $wpdb->get_charset_collate();
		$stats_table      = self::get_stats_table();
		$logs_table       = self::get_logs_table();
		$mappings_table   = self::get_mappings_table();

		$sql_mappings = "CREATE TABLE {$mappings_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subdomain varchar(255) NOT NULL,
			mapping_type varchar(10) NOT NULL DEFAULT 'post',
			post_id bigint(20) unsigned DEFAULT NULL,
			redirect_url text DEFAULT NULL,
			redirect_code smallint(3) unsigned NOT NULL DEFAULT 301,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY subdomain (subdomain),
			KEY post_id (post_id),
			KEY is_active (is_active),
			KEY mapping_type (mapping_type)
		) {$charset_collate};";

		$sql_stats = "CREATE TABLE {$stats_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subdomain varchar(255) NOT NULL,
			target_path varchar(255) NOT NULL,
			redirect_count bigint(20) unsigned NOT NULL DEFAULT 0,
			last_redirect datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY subdomain (subdomain),
			KEY redirect_count (redirect_count),
			KEY last_redirect (last_redirect)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subdomain varchar(255) NOT NULL,
			target_path varchar(255) NOT NULL,
			source_url text NOT NULL,
			user_agent text DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			referer text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY subdomain (subdomain),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_mappings );
		dbDelta( $sql_stats );
		dbDelta( $sql_logs );

		update_option( 'src_db_version', self::DB_VERSION );
	}

	/**
	 * Drop all plugin tables.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::get_stats_table() ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::get_logs_table() ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::get_mappings_table() ) );

		delete_option( 'src_db_version' );
	}

	/**
	 * Execute a callback for each site in the network.
	 *
	 * In single-site installs, the callback is executed once for the current site.
	 * In multisite installs, the callback is executed for each site in the network.
	 *
	 * @param callable $callback The callback to execute for each site.
	 * @return void
	 */
	public static function for_each_site( callable $callback ): void {
		if ( ! is_multisite() ) {
			call_user_func( $callback );
			return;
		}

		$sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );
			call_user_func( $callback );
			restore_current_blog();
		}
	}
}

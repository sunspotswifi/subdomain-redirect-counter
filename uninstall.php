<?php
/**
 * Uninstall script.
 *
 * Runs when the plugin is deleted through the WordPress admin.
 * Handles both single-site and multisite installations.
 *
 * @package Subdomain_Redirect_Counter
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up a single site's data.
 *
 * @return void
 */
function src_uninstall_single_site(): void {
	global $wpdb;

	// Delete options.
	delete_option( 'src_settings' );
	delete_option( 'src_db_version' );

	// Clear scheduled cron events.
	$timestamp = wp_next_scheduled( 'src_log_rotation_cron' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'src_log_rotation_cron' );
	}

	// Drop custom tables.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}src_statistics" );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}src_logs" );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}src_mappings" );
}

// Handle multisite vs single site.
if ( is_multisite() ) {
	// Get all site IDs in the network.
	$src_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0, // All sites.
		)
	);

	foreach ( $src_sites as $src_site_id ) {
		switch_to_blog( $src_site_id );
		src_uninstall_single_site();
		restore_current_blog();
	}
} else {
	// Single site uninstall.
	src_uninstall_single_site();
}

// Clear any cached data.
wp_cache_flush();

<?php
/**
 * Sunrise file for early domain redirect handling.
 *
 * This file must be copied to wp-content/sunrise.php and SUNRISE must be
 * defined in wp-config.php for it to work.
 *
 * Add this line to wp-config.php (before the "That's all, stop editing!" comment):
 *   define( 'SUNRISE', true );
 *
 * @package Subdomain_Redirect_Counter
 * @since 1.5.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only process in multisite context.
if ( ! is_multisite() ) {
	return;
}

/**
 * Handle domain redirects before WordPress multisite site lookup.
 *
 * This runs very early, before most WordPress functions are available.
 * We must use $wpdb directly and raw PHP functions.
 */
function src_sunrise_handle_domain_redirect(): void {
	global $wpdb;

	// Get current host.
	$host = '';
	if ( isset( $_SERVER['HTTP_HOST'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$host = strtolower( wp_unslash( $_SERVER['HTTP_HOST'] ) );
	}

	// Remove port if present.
	$host = preg_replace( '/:\d+$/', '', $host );

	if ( empty( $host ) ) {
		return;
	}

	// Get the main site's options table prefix.
	// In multisite, the main site uses the base prefix.
	$main_prefix = $wpdb->base_prefix;

	// Try to get redirect_domains from main site's src_settings option.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$settings_row = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$main_prefix}options WHERE option_name = %s",
			'src_settings'
		)
	);

	if ( empty( $settings_row ) ) {
		return;
	}

	$settings = maybe_unserialize( $settings_row );

	if ( ! is_array( $settings ) || empty( $settings['redirect_domains'] ) ) {
		return;
	}

	$redirect_domains = $settings['redirect_domains'];

	// Check if current host matches any redirect domain.
	foreach ( $redirect_domains as $redirect ) {
		if ( empty( $redirect['domain'] ) || empty( $redirect['target_url'] ) ) {
			continue;
		}

		$from_domain   = strtolower( trim( $redirect['domain'] ) );
		$redirect_code = isset( $redirect['redirect_code'] ) ? (int) $redirect['redirect_code'] : 301;
		$keep_path     = ! empty( $redirect['keep_path'] );
		$keep_query    = ! empty( $redirect['keep_query'] );

		// Normalize: remove www prefix for matching.
		$from_normalized = preg_replace( '/^www\./', '', $from_domain );
		$host_normalized = preg_replace( '/^www\./', '', $host );

		// Check for exact match (with or without www).
		if ( $host_normalized === $from_normalized ) {
			$target_url = $redirect['target_url'];

			// Parse the request URI into path and query.
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
			$parsed      = parse_url( $request_uri );
			$path        = isset( $parsed['path'] ) ? $parsed['path'] : '/';
			$query       = isset( $parsed['query'] ) ? $parsed['query'] : '';

			// Append path if enabled and not just root.
			if ( $keep_path && '/' !== $path && ! empty( $path ) ) {
				$target_url = rtrim( $target_url, '/' ) . $path;
			}

			// Append query string if enabled.
			if ( $keep_query && ! empty( $query ) ) {
				$separator   = ( strpos( $target_url, '?' ) !== false ) ? '&' : '?';
				$target_url .= $separator . $query;
			}

			// Try to record the redirect in statistics and logs.
			src_sunrise_record_stat( $main_prefix, $from_normalized, $target_url );

			// Log if enabled.
			$logging_enabled = ! empty( $settings['logging_enabled'] );
			if ( $logging_enabled ) {
				src_sunrise_record_log( $main_prefix, $from_normalized, $target_url, $host );
			}

			// Perform the redirect.
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			header( 'Location: ' . $target_url, true, $redirect_code );
			exit;
		}
	}
}

/**
 * Record a domain redirect statistic.
 *
 * This is a simplified version since most WordPress functions aren't available.
 *
 * @param string $prefix      The database table prefix.
 * @param string $from_domain The domain that was redirected.
 * @param string $target_url  The target URL for the redirect.
 */
function src_sunrise_record_stat( string $prefix, string $from_domain, string $target_url ): void {
	global $wpdb;

	// Use @ prefix to identify domain redirects (vs subdomain redirects).
	$subdomain = '@' . $from_domain;

	// Use gmdate since current_time() may not be available at sunrise.
	$now = gmdate( 'Y-m-d H:i:s' );

	// Try to update existing record.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$prefix}src_statistics
			SET redirect_count = redirect_count + 1,
			    last_redirect = %s
			WHERE subdomain = %s",
			$now,
			$subdomain
		)
	);

	// If no row was updated, insert a new one.
	if ( 0 === $updated ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$prefix}src_statistics (subdomain, target_path, redirect_count, last_redirect)
				VALUES (%s, %s, 1, %s)",
				$subdomain,
				$target_url,
				$now
			)
		);
	}
}

/**
 * Record a domain redirect log entry.
 *
 * @param string $prefix      The database table prefix.
 * @param string $from_domain The domain that was redirected.
 * @param string $target_url  The target URL for the redirect.
 * @param string $source_host The original host.
 */
function src_sunrise_record_log( string $prefix, string $from_domain, string $target_url, string $source_host ): void {
	global $wpdb;

	// Use @ prefix to identify domain redirects.
	$subdomain = '@' . $from_domain;

	// Build source URL.
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	$source_url  = 'https://' . $source_host . $request_uri;

	// Get user agent.
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 500 ) : '';

	// Get IP address.
	$ip_address = '';
	if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$forwarded = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$ip_address = trim( $forwarded[0] );
	} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip_address = $_SERVER['REMOTE_ADDR'];
	}

	// Get referer.
	$referer = isset( $_SERVER['HTTP_REFERER'] ) ? substr( $_SERVER['HTTP_REFERER'], 0, 500 ) : '';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$prefix}src_logs (subdomain, target_path, source_url, user_agent, ip_address, referer)
			VALUES (%s, %s, %s, %s, %s, %s)",
			$subdomain,
			$target_url,
			$source_url,
			$user_agent,
			$ip_address,
			$referer
		)
	);
}

// Execute the domain redirect check.
src_sunrise_handle_domain_redirect();

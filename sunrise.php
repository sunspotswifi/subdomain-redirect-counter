<?php
/**
 * Sunrise file for early domain redirect handling.
 *
 * Since 1.6.0 installation is automatic: the plugin writes a small loader to
 * wp-content/sunrise.php that requires this file from the plugin directory
 * (so plugin updates apply without re-copying), and the settings page offers
 * a one-click install that also adds the SUNRISE constant to wp-config.php.
 * See Settings → Subdomain Redirects → Domain Redirects.
 *
 * Manual fallback: copy this file to wp-content/sunrise.php and add this line
 * to wp-config.php (before the "That's all, stop editing!" comment):
 *   define( 'SUNRISE', true );
 *
 * IMPORTANT — wp-content/sunrise.php is a singleton: WordPress loads exactly one
 * such file. If another plugin already owns it (WPMU DEV Domain Mapping, Mercator,
 * or any other domain-mapping solution), do NOT overwrite it. Instead, append this
 * file's contents to the existing sunrise.php — this code returns early when no
 * configured domain matches, so it is safe to run after another handler.
 *
 * NOTE — settings are read from the MAIN site only. Because sunrise runs before
 * the requested site is even resolved, redirect domains must be configured in the
 * main site's Subdomain Redirects settings; entries saved on subsites never fire
 * here (and unmapped alias hosts would die at "site not found" before any plugin
 * could handle them).
 *
 * Optional wp-config.php constants:
 *   define( 'SRC_TRUST_X_FORWARDED_FOR', true ); // Trust the X-Forwarded-For header
 *     for logged IPs. Only enable behind a proxy/load balancer that overwrites the
 *     header; otherwise it is client-spoofable. Default: use REMOTE_ADDR.
 *   define( 'SRC_SUNRISE_LOG_THROTTLE', 10 );    // Minimum seconds between log rows
 *     per domain, limiting log flooding from pre-firewall traffic (sunrise runs
 *     before security plugins such as Defender can block banned IPs). Set 0 to
 *     disable throttling. Default: 10. Statistics counts are never throttled.
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
 * Check if a string is serialized data.
 *
 * This is a simplified version since WordPress's is_serialized() isn't available at sunrise.
 *
 * @param string $data The string to check.
 * @return bool True if serialized, false otherwise.
 */
function src_sunrise_is_serialized( $data ): bool {
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
	if ( 'N;' === $data ) {
		return true;
	}
	if ( strlen( $data ) < 4 ) {
		return false;
	}
	if ( ':' !== $data[1] ) {
		return false;
	}
	$lastc = substr( $data, -1 );
	if ( ';' !== $lastc && '}' !== $lastc ) {
		return false;
	}
	$token = $data[0];
	switch ( $token ) {
		case 's':
			if ( '"' !== substr( $data, -2, 1 ) ) {
				return false;
			}
			// Fall through.
		case 'a':
		case 'O':
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b':
		case 'i':
		case 'd':
			return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$/", $data );
	}
	return false;
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
		// Note: Using stripslashes() instead of wp_unslash() since WP functions aren't available at
		// sunrise; the value is only compared against admin-configured domains, never output or stored.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$host = strtolower( stripslashes( $_SERVER['HTTP_HOST'] ) );
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
	// The table name is built from $wpdb->base_prefix, not user input.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$settings_row = $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $main_prefix comes from $wpdb.
			"SELECT option_value FROM {$main_prefix}options WHERE option_name = %s",
			'src_settings'
		)
	);

	if ( empty( $settings_row ) ) {
		return;
	}

	// Note: Using native unserialize() instead of maybe_unserialize() since WP functions aren't
	// available at sunrise. Errors are silenced because malformed option data must not fatal here.
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged
	$settings = src_sunrise_is_serialized( $settings_row ) ? @unserialize( $settings_row ) : $settings_row;

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

		// Entries saved before 1.6.0 lack the keep flags; legacy behavior kept path and query.
		// This logic must stay in sync with SRC_Interceptor::maybe_do_domain_redirect().
		$keep_path  = array_key_exists( 'keep_path', $redirect ) ? ! empty( $redirect['keep_path'] ) : true;
		$keep_query = array_key_exists( 'keep_query', $redirect ) ? ! empty( $redirect['keep_query'] ) : true;

		// Normalize: remove www prefix for matching.
		$from_normalized = preg_replace( '/^www\./', '', $from_domain );
		$host_normalized = preg_replace( '/^www\./', '', $host );

		// Check for exact match (with or without www).
		if ( $host_normalized === $from_normalized ) {
			$target_url = $redirect['target_url'];

			// Parse the request URI into path and query. Raw superglobal use is required at
			// sunrise; the value is only re-attached to an admin-configured redirect target.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- wp_parse_url() unavailable at sunrise.
			$parsed = parse_url( $request_uri );
			$path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
			$query  = isset( $parsed['query'] ) ? $parsed['query'] : '';

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
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- wp_redirect() unavailable at sunrise; intentional external redirect.
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

	// Try to update existing record. target_path is refreshed so statistics
	// reflect the currently configured target, not the first-ever one.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$updated = $wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $prefix comes from $wpdb.
			"UPDATE {$prefix}src_statistics
			SET redirect_count = redirect_count + 1,
			    target_path = %s,
			    last_redirect = %s
			WHERE subdomain = %s",
			$target_url,
			$now,
			$subdomain
		)
	);

	// If no row was updated, insert a new one.
	if ( 0 === $updated ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $prefix comes from $wpdb.
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
 * Get the client IP address for logging.
 *
 * Uses REMOTE_ADDR unless SRC_TRUST_X_FORWARDED_FOR is defined and true,
 * mirroring SRC_Logger::get_client_ip(). X-Forwarded-For is client-spoofable
 * and must only be trusted behind a proxy that overwrites it.
 *
 * @return string A validated IP address, or empty string.
 */
function src_sunrise_get_ip(): string {
	$ip = '';

	if ( defined( 'SRC_TRUST_X_FORWARDED_FOR' ) && SRC_TRUST_X_FORWARDED_FOR && isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated with filter_var() below.
		$forwarded = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$ip        = trim( $forwarded[0] );
	} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated with filter_var() below.
		$ip = $_SERVER['REMOTE_ADDR'];
	}

	if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return $ip;
	}

	return '';
}

/**
 * Anonymize an IP address for privacy.
 *
 * WordPress's wp_privacy_anonymize_ip() is not loaded at sunrise, so this
 * ports the fallback logic from SRC_Logger::anonymize_ip().
 *
 * @param string $ip The IP address to anonymize.
 * @return string The anonymized IP address, or empty string.
 */
function src_sunrise_anonymize_ip( string $ip ): string {
	if ( empty( $ip ) ) {
		return '';
	}

	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		// Remove last octet for IPv4.
		return preg_replace( '/\.\d+$/', '.0', $ip );
	}

	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		// Remove last 80 bits for IPv6.
		return substr( $ip, 0, strrpos( $ip, ':' ) ) . ':0:0:0:0:0';
	}

	return '';
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

	// Throttle log inserts: sunrise runs before firewall plugins (e.g. Defender)
	// can block banned IPs, so unthrottled logging is a flooding vector.
	$throttle = defined( 'SRC_SUNRISE_LOG_THROTTLE' ) ? (int) SRC_SUNRISE_LOG_THROTTLE : 10;
	if ( $throttle > 0 ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$recent = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $prefix comes from $wpdb.
				"SELECT COUNT(*) FROM {$prefix}src_logs
				WHERE subdomain = %s AND created_at > DATE_SUB( NOW(), INTERVAL %d SECOND )",
				$subdomain,
				$throttle
			)
		);

		if ( (int) $recent > 0 ) {
			return;
		}
	}

	// Build source URL. Raw superglobal use is required at sunrise.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	$source_url  = 'https://' . $source_host . $request_uri;

	// Get user agent.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Length-capped raw header; sanitization helpers unavailable at sunrise.
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 500 ) : '';

	// Get anonymized IP address (matches SRC_Logger behavior).
	$ip_address = src_sunrise_anonymize_ip( src_sunrise_get_ip() );

	// Get referer.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Length-capped raw header; sanitization helpers unavailable at sunrise.
	$referer = isset( $_SERVER['HTTP_REFERER'] ) ? substr( $_SERVER['HTTP_REFERER'], 0, 500 ) : '';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $prefix comes from $wpdb.
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

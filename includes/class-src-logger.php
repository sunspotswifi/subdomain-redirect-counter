<?php
/**
 * Logger class.
 *
 * @package Subdomain_Redirect_Counter
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles logging of redirect events.
 *
 * @since 1.0.0
 */
class SRC_Logger {

	/**
	 * Log a redirect event.
	 *
	 * @param string $subdomain   The subdomain that was accessed.
	 * @param string $target_path The path it was redirected to.
	 * @param string $source_url  The original URL.
	 * @return bool Whether the log was created successfully.
	 */
	public static function log( string $subdomain, string $target_path, string $source_url ): bool {
		$settings = get_option( 'src_settings', array() );

		if ( empty( $settings['logging_enabled'] ) ) {
			return false;
		}

		global $wpdb;

		$table = SRC_Database::get_logs_table();

		// Get user agent safely.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		// Get IP address safely.
		$ip_address = self::get_client_ip();

		// Get referer safely.
		$referer = isset( $_SERVER['HTTP_REFERER'] )
			? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
			: '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'subdomain'   => $subdomain,
				'target_path' => $target_path,
				'source_url'  => $source_url,
				'user_agent'  => $user_agent,
				'ip_address'  => self::anonymize_ip( $ip_address ),
				'referer'     => $referer,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string The client IP address.
	 */
	private static function get_client_ip(): string {
		$ip = '';

		// Check for various proxy headers, but prioritize REMOTE_ADDR for security.
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Validate IP.
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}

		return '';
	}

	/**
	 * Anonymize IP address for privacy.
	 *
	 * @param string $ip The IP address to anonymize.
	 * @return string The anonymized IP address.
	 */
	private static function anonymize_ip( string $ip ): string {
		if ( empty( $ip ) ) {
			return '';
		}

		// Use WordPress's built-in function if available (WP 4.9.6+).
		if ( function_exists( 'wp_privacy_anonymize_ip' ) ) {
			return wp_privacy_anonymize_ip( $ip );
		}

		// Fallback for older WordPress versions.
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
	 * Get a single log entry by ID.
	 *
	 * @param int $id The log entry ID.
	 * @return object|null The log entry or null if not found.
	 */
	public static function get_log_by_id( int $id ): ?object {
		global $wpdb;

		$table = SRC_Database::get_logs_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$id
			)
		);

		return $result ? $result : null;
	}

	/**
	 * Get logs with pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of log records.
	 */
	public static function get_logs( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'orderby'            => 'created_at',
			'order'              => 'DESC',
			'limit'              => 50,
			'offset'             => 0,
			'subdomain_like'     => '',
			'subdomain_not_like' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$table   = SRC_Database::get_logs_table();
		$orderby = in_array( $args['orderby'], array( 'subdomain', 'created_at' ), true )
			? $args['orderby']
			: 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit   = absint( $args['limit'] );
		$offset  = absint( $args['offset'] );

		// Build WHERE clause for filtering.
		$where = '1=1';
		if ( ! empty( $args['subdomain_like'] ) ) {
			$where .= $wpdb->prepare( ' AND subdomain LIKE %s', $args['subdomain_like'] );
		}
		if ( ! empty( $args['subdomain_not_like'] ) ) {
			$where .= $wpdb->prepare( ' AND subdomain NOT LIKE %s', $args['subdomain_not_like'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order and $where are validated/escaped above.
				"SELECT * FROM %i WHERE {$where} ORDER BY %i {$order} LIMIT %d OFFSET %d",
				$table,
				$orderby,
				$limit,
				$offset
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Get total count of log entries.
	 *
	 * @param array $args Optional filter arguments (subdomain_like, subdomain_not_like).
	 * @return int Total number of log entries.
	 */
	public static function get_total_count( array $args = array() ): int {
		global $wpdb;

		$table = SRC_Database::get_logs_table();

		// Build WHERE clause for filtering.
		$where = '1=1';
		if ( ! empty( $args['subdomain_like'] ) ) {
			$where .= $wpdb->prepare( ' AND subdomain LIKE %s', $args['subdomain_like'] );
		}
		if ( ! empty( $args['subdomain_not_like'] ) ) {
			$where .= $wpdb->prepare( ' AND subdomain NOT LIKE %s', $args['subdomain_not_like'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is validated/escaped above.
				"SELECT COUNT(*) FROM %i WHERE {$where}",
				$table
			)
		);

		return (int) $count;
	}

	/**
	 * Clear all logs.
	 *
	 * @return bool Whether the operation was successful.
	 */
	public static function clear_logs(): bool {
		global $wpdb;

		$table = SRC_Database::get_logs_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare( 'TRUNCATE TABLE %i', $table )
		);

		return false !== $result;
	}

	/**
	 * Delete logs older than a certain number of days.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of deleted records.
	 */
	public static function delete_old_logs( int $days = 30 ): int {
		global $wpdb;

		$table    = SRC_Database::get_logs_table();
		$cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				$table,
				$cutoff
			)
		);

		return (int) $result;
	}
}

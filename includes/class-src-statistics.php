<?php
/**
 * Statistics handler class.
 *
 * @package Subdomain_Redirect_Counter
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles statistics tracking and retrieval.
 *
 * @since 1.0.0
 */
class SRC_Statistics {

	/**
	 * Record a redirect.
	 *
	 * @param string $subdomain   The subdomain that was accessed.
	 * @param string $target_path The path it was redirected to.
	 * @return bool Whether the operation was successful.
	 */
	public static function record_redirect( string $subdomain, string $target_path ): bool {
		global $wpdb;

		$table = SRC_Database::get_stats_table();

		// Try to update existing record first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET redirect_count = redirect_count + 1, last_redirect = %s WHERE subdomain = %s',
				$table,
				current_time( 'mysql' ),
				$subdomain
			)
		);

		// If no rows updated, insert new record.
		if ( 0 === $updated ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				array(
					'subdomain'      => $subdomain,
					'target_path'    => $target_path,
					'redirect_count' => 1,
					'last_redirect'  => current_time( 'mysql' ),
					'created_at'     => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * Get all statistics.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of statistics records.
	 */
	public static function get_all( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'orderby' => 'redirect_count',
			'order'   => 'DESC',
			'limit'   => 50,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$table   = SRC_Database::get_stats_table();
		$orderby = in_array( $args['orderby'], array( 'subdomain', 'redirect_count', 'last_redirect', 'created_at' ), true )
			? $args['orderby']
			: 'redirect_count';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit   = absint( $args['limit'] );
		$offset  = absint( $args['offset'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order is validated above.
				"SELECT * FROM %i ORDER BY %i {$order} LIMIT %d OFFSET %d",
				$table,
				$orderby,
				$limit,
				$offset
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Get total count of records.
	 *
	 * @return int Total number of records.
	 */
	public static function get_total_count(): int {
		global $wpdb;

		$table = SRC_Database::get_stats_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
		);

		return (int) $count;
	}

	/**
	 * Get total redirect count.
	 *
	 * @return int Total number of redirects.
	 */
	public static function get_total_redirects(): int {
		global $wpdb;

		$table = SRC_Database::get_stats_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare( 'SELECT SUM(redirect_count) FROM %i', $table )
		);

		return (int) $count;
	}

	/**
	 * Get statistics for a specific subdomain.
	 *
	 * @param string $subdomain The subdomain to look up.
	 * @return object|null The statistics record or null if not found.
	 */
	public static function get_by_subdomain( string $subdomain ): ?object {
		global $wpdb;

		$table = SRC_Database::get_stats_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE subdomain = %s',
				$table,
				$subdomain
			)
		);

		return $result;
	}

	/**
	 * Get top subdomains by redirect count.
	 *
	 * @param int $limit Number of results to return.
	 * @return array Array of top subdomains.
	 */
	public static function get_top_subdomains( int $limit = 10 ): array {
		return self::get_all(
			array(
				'orderby' => 'redirect_count',
				'order'   => 'DESC',
				'limit'   => $limit,
			)
		);
	}

	/**
	 * Get recent redirects.
	 *
	 * @param int $limit Number of results to return.
	 * @return array Array of recent redirects.
	 */
	public static function get_recent( int $limit = 10 ): array {
		return self::get_all(
			array(
				'orderby' => 'last_redirect',
				'order'   => 'DESC',
				'limit'   => $limit,
			)
		);
	}

	/**
	 * Reset statistics for a specific subdomain.
	 *
	 * @param string $subdomain The subdomain to reset.
	 * @return bool Whether the operation was successful.
	 */
	public static function reset_subdomain( string $subdomain ): bool {
		global $wpdb;

		$table = SRC_Database::get_stats_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table,
			array( 'subdomain' => $subdomain ),
			array( '%s' )
		);

		return false !== $result;
	}

	/**
	 * Reset all statistics.
	 *
	 * @return bool Whether the operation was successful.
	 */
	public static function reset_all(): bool {
		global $wpdb;

		$table = SRC_Database::get_stats_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare( 'TRUNCATE TABLE %i', $table )
		);

		return false !== $result;
	}

	/**
	 * Get statistics summary.
	 *
	 * @return array Summary data.
	 */
	public static function get_summary(): array {
		global $wpdb;

		$table = SRC_Database::get_stats_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT
					COUNT(*) as total_subdomains,
					COALESCE(SUM(redirect_count), 0) as total_redirects,
					MAX(last_redirect) as last_redirect_time
				FROM %i',
				$table
			)
		);

		return array(
			'total_subdomains'   => (int) ( $stats->total_subdomains ?? 0 ),
			'total_redirects'    => (int) ( $stats->total_redirects ?? 0 ),
			'last_redirect_time' => $stats->last_redirect_time ?? null,
		);
	}
}

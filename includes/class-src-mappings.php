<?php
/**
 * Mappings handler class.
 *
 * @package Subdomain_Redirect_Counter
 * @since 1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles subdomain to post/page mappings and URL redirects.
 *
 * @since 1.1.0
 */
class SRC_Mappings {

	/**
	 * Mapping type: Post/Page.
	 */
	const TYPE_POST = 'post';

	/**
	 * Mapping type: URL redirect.
	 */
	const TYPE_URL = 'url';

	/**
	 * Mapping type: Homepage redirect.
	 */
	const TYPE_HOME = 'home';

	/**
	 * Valid redirect codes.
	 */
	const VALID_REDIRECT_CODES = array( 301, 302, 307, 308 );

	/**
	 * Get all mappings.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of mapping records.
	 */
	public static function get_all( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'orderby'     => 'subdomain',
			'order'       => 'ASC',
			'limit'       => 50,
			'offset'      => 0,
			'active_only' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		$table   = SRC_Database::get_mappings_table();
		$orderby = in_array( $args['orderby'], array( 'subdomain', 'post_id', 'created_at', 'updated_at' ), true )
			? $args['orderby']
			: 'subdomain';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$limit   = absint( $args['limit'] );
		$offset  = absint( $args['offset'] );

		$where = '';
		if ( $args['active_only'] ) {
			$where = 'WHERE is_active = 1';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where and $order are validated above.
				"SELECT * FROM %i {$where} ORDER BY %i {$order} LIMIT %d OFFSET %d",
				$table,
				$orderby,
				$limit,
				$offset
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Get total count of mappings.
	 *
	 * @param bool $active_only Whether to count only active mappings.
	 * @return int Total number of mappings.
	 */
	public static function get_total_count( bool $active_only = false ): int {
		global $wpdb;

		$table = SRC_Database::get_mappings_table();
		$where = $active_only ? 'WHERE is_active = 1' : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is a static string.
			$wpdb->prepare( "SELECT COUNT(*) FROM %i {$where}", $table )
		);

		return (int) $count;
	}

	/**
	 * Get a mapping by subdomain.
	 *
	 * @param string $subdomain The subdomain to look up.
	 * @return object|null The mapping record or null if not found.
	 */
	public static function get_by_subdomain( string $subdomain ): ?object {
		global $wpdb;

		$table = SRC_Database::get_mappings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE subdomain = %s AND is_active = 1',
				$table,
				$subdomain
			)
		);

		return $result;
	}

	/**
	 * Get a mapping by ID.
	 *
	 * @param int $id The mapping ID.
	 * @return object|null The mapping record or null if not found.
	 */
	public static function get_by_id( int $id ): ?object {
		global $wpdb;

		$table = SRC_Database::get_mappings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$id
			)
		);

		return $result;
	}

	/**
	 * Add a new mapping.
	 *
	 * @param string      $subdomain     The subdomain.
	 * @param int|null    $post_id       The post/page ID to map to (for post type).
	 * @param string      $mapping_type  The mapping type ('post' or 'url').
	 * @param string|null $redirect_url  The redirect URL (for url type).
	 * @param int         $redirect_code The HTTP redirect code (301, 302, 307, 308).
	 * @return int|false The new mapping ID or false on failure.
	 */
	public static function add( string $subdomain, ?int $post_id = null, string $mapping_type = self::TYPE_POST, ?string $redirect_url = null, int $redirect_code = 301 ) {
		global $wpdb;

		$subdomain     = sanitize_key( $subdomain );
		$mapping_type  = in_array( $mapping_type, array( self::TYPE_POST, self::TYPE_URL, self::TYPE_HOME ), true ) ? $mapping_type : self::TYPE_POST;
		$redirect_code = in_array( $redirect_code, self::VALID_REDIRECT_CODES, true ) ? $redirect_code : 301;

		if ( empty( $subdomain ) ) {
			return false;
		}

		// Validate based on mapping type.
		if ( self::TYPE_POST === $mapping_type ) {
			if ( ! $post_id || $post_id <= 0 ) {
				return false;
			}
			$post = get_post( $post_id );
			if ( ! $post ) {
				return false;
			}
			$redirect_url = null;
		} elseif ( self::TYPE_URL === $mapping_type ) {
			if ( empty( $redirect_url ) ) {
				return false;
			}
			$redirect_url = esc_url_raw( $redirect_url );
			if ( empty( $redirect_url ) ) {
				return false;
			}
			$post_id = null;
		} else {
			// TYPE_HOME - redirect to homepage, no post_id or redirect_url needed.
			$post_id      = null;
			$redirect_url = null;
		}

		$table = SRC_Database::get_mappings_table();

		// Build SQL with proper NULL handling.
		$post_id_sql      = null === $post_id ? 'NULL' : $wpdb->prepare( '%d', $post_id );
		$redirect_url_sql = null === $redirect_url ? 'NULL' : $wpdb->prepare( '%s', $redirect_url );
		$now              = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- INSERT requires no cache.
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $post_id_sql and $redirect_url_sql are prepared above.
				"INSERT INTO %i (subdomain, mapping_type, post_id, redirect_url, redirect_code, is_active, created_at, updated_at) VALUES (%s, %s, {$post_id_sql}, {$redirect_url_sql}, %d, 1, %s, %s)",
				$table,
				$subdomain,
				$mapping_type,
				$redirect_code,
				$now,
				$now
			)
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update a mapping.
	 *
	 * @param int         $id            The mapping ID.
	 * @param string      $subdomain     The subdomain.
	 * @param int|null    $post_id       The post/page ID to map to (for post type).
	 * @param string      $mapping_type  The mapping type ('post' or 'url').
	 * @param string|null $redirect_url  The redirect URL (for url type).
	 * @param int         $redirect_code The HTTP redirect code (301, 302, 307, 308).
	 * @return bool Whether the update was successful.
	 */
	public static function update( int $id, string $subdomain, ?int $post_id = null, string $mapping_type = self::TYPE_POST, ?string $redirect_url = null, int $redirect_code = 301 ): bool {
		global $wpdb;

		$subdomain     = sanitize_key( $subdomain );
		$mapping_type  = in_array( $mapping_type, array( self::TYPE_POST, self::TYPE_URL, self::TYPE_HOME ), true ) ? $mapping_type : self::TYPE_POST;
		$redirect_code = in_array( $redirect_code, self::VALID_REDIRECT_CODES, true ) ? $redirect_code : 301;

		if ( empty( $subdomain ) ) {
			return false;
		}

		// Validate based on mapping type.
		if ( self::TYPE_POST === $mapping_type ) {
			if ( ! $post_id || $post_id <= 0 ) {
				return false;
			}
			$post = get_post( $post_id );
			if ( ! $post ) {
				return false;
			}
			$redirect_url = null;
		} elseif ( self::TYPE_URL === $mapping_type ) {
			if ( empty( $redirect_url ) ) {
				return false;
			}
			$redirect_url = esc_url_raw( $redirect_url );
			if ( empty( $redirect_url ) ) {
				return false;
			}
			$post_id = null;
		} else {
			// TYPE_HOME - redirect to homepage, no post_id or redirect_url needed.
			$post_id      = null;
			$redirect_url = null;
		}

		$table = SRC_Database::get_mappings_table();

		// Build SQL with proper NULL handling.
		$post_id_sql      = null === $post_id ? 'NULL' : $wpdb->prepare( '%d', $post_id );
		$redirect_url_sql = null === $redirect_url ? 'NULL' : $wpdb->prepare( '%s', $redirect_url );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $post_id_sql and $redirect_url_sql are prepared above.
				"UPDATE %i SET subdomain = %s, mapping_type = %s, post_id = {$post_id_sql}, redirect_url = {$redirect_url_sql}, redirect_code = %d, updated_at = %s WHERE id = %d",
				$table,
				$subdomain,
				$mapping_type,
				$redirect_code,
				current_time( 'mysql' ),
				$id
			)
		);

		return false !== $result;
	}

	/**
	 * Delete a mapping.
	 *
	 * @param int $id The mapping ID.
	 * @return bool Whether the deletion was successful.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$table = SRC_Database::get_mappings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Toggle mapping active status.
	 *
	 * @param int  $id        The mapping ID.
	 * @param bool $is_active Whether the mapping should be active.
	 * @return bool Whether the update was successful.
	 */
	public static function set_active( int $id, bool $is_active ): bool {
		global $wpdb;

		$table = SRC_Database::get_mappings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array(
				'is_active'  => $is_active ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Check if a subdomain is already mapped.
	 *
	 * @param string   $subdomain  The subdomain to check.
	 * @param int|null $exclude_id Mapping ID to exclude from the check.
	 * @return bool Whether the subdomain is already mapped.
	 */
	public static function subdomain_exists( string $subdomain, ?int $exclude_id = null ): bool {
		global $wpdb;

		$table     = SRC_Database::get_mappings_table();
		$subdomain = sanitize_key( $subdomain );

		if ( $exclude_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE subdomain = %s AND id != %d',
					$table,
					$subdomain,
					$exclude_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE subdomain = %s',
					$table,
					$subdomain
				)
			);
		}

		return (int) $result > 0;
	}

	/**
	 * Get the post/page for a subdomain mapping.
	 *
	 * @param string $subdomain The subdomain.
	 * @return WP_Post|null The mapped post or null.
	 */
	public static function get_mapped_post( string $subdomain ): ?WP_Post {
		$mapping = self::get_by_subdomain( $subdomain );

		if ( ! $mapping ) {
			return null;
		}

		// Only return post for post-type mappings.
		$mapping_type = $mapping->mapping_type ?? self::TYPE_POST;
		if ( self::TYPE_POST !== $mapping_type ) {
			return null;
		}

		$post = get_post( $mapping->post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		return $post;
	}

	/**
	 * Get the redirect URL for a subdomain mapping.
	 *
	 * @param string $subdomain The subdomain.
	 * @return string|null The redirect URL or null.
	 */
	public static function get_redirect_url( string $subdomain ): ?string {
		$mapping = self::get_by_subdomain( $subdomain );

		if ( ! $mapping ) {
			return null;
		}

		$mapping_type = $mapping->mapping_type ?? self::TYPE_POST;
		if ( self::TYPE_URL !== $mapping_type ) {
			return null;
		}

		return $mapping->redirect_url ? $mapping->redirect_url : null;
	}

	/**
	 * Get the mapping type for a subdomain.
	 *
	 * @param string $subdomain The subdomain.
	 * @return string|null The mapping type or null if not found.
	 */
	public static function get_mapping_type( string $subdomain ): ?string {
		$mapping = self::get_by_subdomain( $subdomain );

		if ( ! $mapping ) {
			return null;
		}

		return $mapping->mapping_type ?? self::TYPE_POST;
	}

	/**
	 * Get the redirect code for a subdomain mapping.
	 *
	 * @param string $subdomain The subdomain.
	 * @return int The redirect code (defaults to 301 if not found).
	 */
	public static function get_redirect_code( string $subdomain ): int {
		$mapping = self::get_by_subdomain( $subdomain );

		if ( ! $mapping ) {
			return 301;
		}

		$code = (int) ( $mapping->redirect_code ?? 301 );

		return in_array( $code, self::VALID_REDIRECT_CODES, true ) ? $code : 301;
	}

	/**
	 * Check if a subdomain is configured for homepage redirect.
	 *
	 * @param string $subdomain The subdomain.
	 * @return bool Whether the subdomain should redirect to homepage.
	 */
	public static function is_home_redirect( string $subdomain ): bool {
		$mapping = self::get_by_subdomain( $subdomain );

		if ( ! $mapping ) {
			return false;
		}

		$mapping_type = $mapping->mapping_type ?? self::TYPE_POST;

		return self::TYPE_HOME === $mapping_type;
	}
}

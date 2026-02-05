<?php
/**
 * Network Admin class.
 *
 * Provides network-level administration for multisite installations.
 *
 * @package Subdomain_Redirect_Counter
 * @since 1.5.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles network admin interface for multisite.
 *
 * @since 1.5.0
 */
class SRC_Network_Admin {

	/**
	 * Single instance of the class.
	 *
	 * @var SRC_Network_Admin|null
	 */
	private static ?SRC_Network_Admin $instance = null;

	/**
	 * Get the single instance.
	 *
	 * @return SRC_Network_Admin
	 */
	public static function get_instance(): SRC_Network_Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'network_admin_menu', array( $this, 'add_network_admin_menu' ) );
		add_action( 'network_admin_notices', array( $this, 'maybe_show_sunrise_notice' ) );

		// Form handlers.
		add_action( 'admin_post_src_network_add_mapping', array( $this, 'handle_add_mapping' ) );
		add_action( 'admin_post_src_network_edit_mapping', array( $this, 'handle_edit_mapping' ) );
		add_action( 'admin_post_src_network_delete_mapping', array( $this, 'handle_delete_mapping' ) );
	}

	/**
	 * Show admin notice if sunrise.php is needed but not installed.
	 *
	 * @return void
	 */
	public function maybe_show_sunrise_notice(): void {
		// Only show on our plugin pages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking current page.
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( strpos( $page, 'src-network' ) !== 0 ) {
			return;
		}

		// Check if there are domain redirects configured.
		$settings = get_option( 'src_settings', array() );
		if ( empty( $settings['redirect_domains'] ) ) {
			return;
		}

		// Check if SUNRISE is defined (meaning sunrise.php is active).
		if ( defined( 'SUNRISE' ) && SUNRISE ) {
			return;
		}

		// Check if sunrise.php exists in wp-content.
		$sunrise_path = WP_CONTENT_DIR . '/sunrise.php';
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Subdomain Redirect Counter:', 'subdomain-redirect-counter' ); ?></strong>
				<?php esc_html_e( 'Domain redirects are configured but sunrise.php is not active. For domain redirects to work on multisite, you need to:', 'subdomain-redirect-counter' ); ?>
			</p>
			<ol>
				<li>
					<?php
					printf(
						/* translators: %s: source file path */
						esc_html__( 'Copy %s to wp-content/sunrise.php', 'subdomain-redirect-counter' ),
						'<code>' . esc_html( SRC_PLUGIN_DIR . 'sunrise.php' ) . '</code>'
					);
					?>
				</li>
				<li>
					<?php
					printf(
						/* translators: %s: PHP code */
						esc_html__( 'Add %s to wp-config.php (before the "That\'s all, stop editing!" comment)', 'subdomain-redirect-counter' ),
						'<code>define( \'SUNRISE\', true );</code>'
					);
					?>
				</li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Add network admin menu.
	 *
	 * @return void
	 */
	public function add_network_admin_menu(): void {
		add_menu_page(
			__( 'Subdomain Redirects', 'subdomain-redirect-counter' ),
			__( 'Subdomain Redirects', 'subdomain-redirect-counter' ),
			'manage_network',
			'src-network-dashboard',
			array( $this, 'render_network_dashboard' ),
			'dashicons-randomize',
			30
		);

		add_submenu_page(
			'src-network-dashboard',
			__( 'Network Dashboard', 'subdomain-redirect-counter' ),
			__( 'Dashboard', 'subdomain-redirect-counter' ),
			'manage_network',
			'src-network-dashboard',
			array( $this, 'render_network_dashboard' )
		);

		add_submenu_page(
			'src-network-dashboard',
			__( 'All Mappings', 'subdomain-redirect-counter' ),
			__( 'All Mappings', 'subdomain-redirect-counter' ),
			'manage_network',
			'src-network-mappings',
			array( $this, 'render_network_mappings' )
		);

		add_submenu_page(
			'src-network-dashboard',
			__( 'Network Statistics', 'subdomain-redirect-counter' ),
			__( 'Statistics', 'subdomain-redirect-counter' ),
			'manage_network',
			'src-network-statistics',
			array( $this, 'render_network_statistics' )
		);
	}

	/**
	 * Get all sites in the network.
	 *
	 * @return array Array of site objects with id, name, and url.
	 */
	private function get_all_sites(): array {
		$sites = get_sites(
			array(
				'number' => 0,
			)
		);

		$result = array();
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			$result[] = (object) array(
				'id'   => $site->blog_id,
				'name' => get_bloginfo( 'name' ),
				'url'  => get_bloginfo( 'url' ),
			);
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Get all mappings across all sites.
	 *
	 * @return array Array of mappings with site info.
	 */
	private function get_all_mappings(): array {
		$all_mappings = array();

		SRC_Database::for_each_site(
			function () use ( &$all_mappings ) {
				$site_id   = get_current_blog_id();
				$site_name = get_bloginfo( 'name' );
				$site_url  = get_bloginfo( 'url' );
				$mappings  = SRC_Mappings::get_all( array( 'limit' => 1000 ) );

				foreach ( $mappings as $mapping ) {
					$mapping->site_id   = $site_id;
					$mapping->site_name = $site_name;
					$mapping->site_url  = $site_url;

					// Get stats for this mapping.
					$stats = SRC_Statistics::get_by_subdomain( $mapping->subdomain );
					$mapping->redirect_count = $stats ? $stats->redirect_count : 0;
					$mapping->last_redirect  = $stats ? $stats->last_redirect : null;

					$all_mappings[] = $mapping;
				}
			}
		);

		return $all_mappings;
	}

	/**
	 * Get network-wide statistics.
	 *
	 * @return object Statistics object.
	 */
	private function get_network_stats(): object {
		$stats = (object) array(
			'total_sites'     => 0,
			'total_mappings'  => 0,
			'total_redirects' => 0,
			'sites'           => array(),
		);

		SRC_Database::for_each_site(
			function () use ( &$stats ) {
				$site_id   = get_current_blog_id();
				$site_name = get_bloginfo( 'name' );

				$mapping_count  = SRC_Mappings::get_total_count();
				$redirect_count = SRC_Statistics::get_total_redirects();

				$stats->total_sites++;
				$stats->total_mappings  += $mapping_count;
				$stats->total_redirects += $redirect_count;

				$stats->sites[] = (object) array(
					'id'        => $site_id,
					'name'      => $site_name,
					'mappings'  => $mapping_count,
					'redirects' => $redirect_count,
				);
			}
		);

		return $stats;
	}

	/**
	 * Render network dashboard page.
	 *
	 * @return void
	 */
	public function render_network_dashboard(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'subdomain-redirect-counter' ) );
		}

		$stats = $this->get_network_stats();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Subdomain Redirects - Network Dashboard', 'subdomain-redirect-counter' ); ?></h1>

			<div class="src-network-stats" style="display: flex; gap: 20px; margin: 20px 0;">
				<div class="src-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1;">
					<h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Total Sites', 'subdomain-redirect-counter' ); ?></h3>
					<p style="font-size: 36px; margin: 0; color: #0073aa;"><?php echo esc_html( number_format_i18n( $stats->total_sites ) ); ?></p>
				</div>
				<div class="src-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1;">
					<h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Total Mappings', 'subdomain-redirect-counter' ); ?></h3>
					<p style="font-size: 36px; margin: 0; color: #0073aa;"><?php echo esc_html( number_format_i18n( $stats->total_mappings ) ); ?></p>
				</div>
				<div class="src-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1;">
					<h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Total Redirects', 'subdomain-redirect-counter' ); ?></h3>
					<p style="font-size: 36px; margin: 0; color: #0073aa;"><?php echo esc_html( number_format_i18n( $stats->total_redirects ) ); ?></p>
				</div>
			</div>

			<h2><?php esc_html_e( 'Sites Overview', 'subdomain-redirect-counter' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Site', 'subdomain-redirect-counter' ); ?></th>
						<th><?php esc_html_e( 'Mappings', 'subdomain-redirect-counter' ); ?></th>
						<th><?php esc_html_e( 'Redirects', 'subdomain-redirect-counter' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'subdomain-redirect-counter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stats->sites as $site ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $site->name ); ?></strong>
								<br><small><?php echo esc_html( 'Site ID: ' . $site->id ); ?></small>
							</td>
							<td><?php echo esc_html( number_format_i18n( $site->mappings ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $site->redirects ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=src-network-mappings&site_id=' . $site->id ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Manage', 'subdomain-redirect-counter' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render network mappings page.
	 *
	 * @return void
	 */
	public function render_network_mappings(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'subdomain-redirect-counter' ) );
		}

		$sites = $this->get_all_sites();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading filter parameter.
		$selected_site = isset( $_GET['site_id'] ) ? absint( $_GET['site_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading action parameter.
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading id parameter.
		$edit_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		// Get message if any.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading message parameter.
		$message = isset( $_GET['message'] ) ? sanitize_key( $_GET['message'] ) : '';

		// Get mappings (filtered by site if selected).
		if ( $selected_site > 0 ) {
			switch_to_blog( $selected_site );
			$mappings = SRC_Mappings::get_all( array( 'limit' => 1000 ) );
			foreach ( $mappings as $mapping ) {
				$mapping->site_id   = $selected_site;
				$mapping->site_name = get_bloginfo( 'name' );
				$stats = SRC_Statistics::get_by_subdomain( $mapping->subdomain );
				$mapping->redirect_count = $stats ? $stats->redirect_count : 0;
				$mapping->last_redirect  = $stats ? $stats->last_redirect : null;
			}
			restore_current_blog();
		} else {
			$mappings = $this->get_all_mappings();
		}

		// Get mapping for edit.
		$edit_mapping = null;
		if ( 'edit' === $action && $edit_id > 0 && $selected_site > 0 ) {
			switch_to_blog( $selected_site );
			$edit_mapping = SRC_Mappings::get_by_id( $edit_id );
			restore_current_blog();
		}

		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'All Mappings', 'subdomain-redirect-counter' ); ?>
				<?php if ( $selected_site > 0 ) : ?>
					<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=src-network-mappings&site_id=' . $selected_site . '&action=add' ) ); ?>" class="page-title-action">
						<?php esc_html_e( 'Add New', 'subdomain-redirect-counter' ); ?>
					</a>
				<?php endif; ?>
			</h1>

			<?php if ( 'added' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Mapping added successfully.', 'subdomain-redirect-counter' ); ?></p></div>
			<?php elseif ( 'updated' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Mapping updated successfully.', 'subdomain-redirect-counter' ); ?></p></div>
			<?php elseif ( 'deleted' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Mapping deleted successfully.', 'subdomain-redirect-counter' ); ?></p></div>
			<?php elseif ( 'error' === $message ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'An error occurred.', 'subdomain-redirect-counter' ); ?></p></div>
			<?php endif; ?>

			<!-- Site Filter -->
			<form method="get" style="margin: 20px 0;">
				<input type="hidden" name="page" value="src-network-mappings">
				<label for="site_id"><strong><?php esc_html_e( 'Select Site:', 'subdomain-redirect-counter' ); ?></strong></label>
				<select name="site_id" id="site_id" onchange="this.form.submit()">
					<option value="0"><?php esc_html_e( '— All Sites —', 'subdomain-redirect-counter' ); ?></option>
					<?php foreach ( $sites as $site ) : ?>
						<option value="<?php echo esc_attr( $site->id ); ?>" <?php selected( $selected_site, $site->id ); ?>>
							<?php echo esc_html( $site->name . ' (ID: ' . $site->id . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</form>

			<?php if ( 'add' === $action && $selected_site > 0 ) : ?>
				<?php $this->render_mapping_form( $selected_site, null ); ?>
			<?php elseif ( 'edit' === $action && $edit_mapping ) : ?>
				<?php $this->render_mapping_form( $selected_site, $edit_mapping ); ?>
			<?php else : ?>
				<!-- Mappings Table -->
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<?php if ( 0 === $selected_site ) : ?>
								<th style="width: 150px;"><?php esc_html_e( 'Site', 'subdomain-redirect-counter' ); ?></th>
							<?php endif; ?>
							<th><?php esc_html_e( 'Subdomain', 'subdomain-redirect-counter' ); ?></th>
							<th><?php esc_html_e( 'Type', 'subdomain-redirect-counter' ); ?></th>
							<th><?php esc_html_e( 'Target', 'subdomain-redirect-counter' ); ?></th>
							<th><?php esc_html_e( 'Code', 'subdomain-redirect-counter' ); ?></th>
							<th><?php esc_html_e( 'Redirects', 'subdomain-redirect-counter' ); ?></th>
							<th><?php esc_html_e( 'Status', 'subdomain-redirect-counter' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'subdomain-redirect-counter' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $mappings ) ) : ?>
							<tr>
								<td colspan="<?php echo 0 === $selected_site ? '8' : '7'; ?>">
									<?php esc_html_e( 'No mappings found.', 'subdomain-redirect-counter' ); ?>
									<?php if ( 0 === $selected_site ) : ?>
										<?php esc_html_e( 'Select a site to add mappings.', 'subdomain-redirect-counter' ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $mappings as $mapping ) : ?>
								<tr>
									<?php if ( 0 === $selected_site ) : ?>
										<td>
											<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=src-network-mappings&site_id=' . $mapping->site_id ) ); ?>">
												<?php echo esc_html( $mapping->site_name ); ?>
											</a>
										</td>
									<?php endif; ?>
									<td><code><?php echo esc_html( $mapping->subdomain ); ?></code></td>
									<td><?php echo esc_html( ucfirst( $mapping->mapping_type ) ); ?></td>
									<td>
										<?php
										if ( 'url' === $mapping->mapping_type ) {
											echo '<code>' . esc_html( $mapping->redirect_url ) . '</code>';
										} elseif ( 'home' === $mapping->mapping_type ) {
											esc_html_e( 'Homepage', 'subdomain-redirect-counter' );
										} else {
											echo esc_html( 'Post #' . $mapping->post_id );
										}
										?>
									</td>
									<td><?php echo esc_html( $mapping->redirect_code ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $mapping->redirect_count ) ); ?></td>
									<td>
										<?php if ( $mapping->is_active ) : ?>
											<span style="color: green;"><?php esc_html_e( 'Active', 'subdomain-redirect-counter' ); ?></span>
										<?php else : ?>
											<span style="color: red;"><?php esc_html_e( 'Inactive', 'subdomain-redirect-counter' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=src-network-mappings&site_id=' . $mapping->site_id . '&action=edit&id=' . $mapping->id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'subdomain-redirect-counter' ); ?>
										</a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=src_network_delete_mapping&site_id=' . $mapping->site_id . '&id=' . $mapping->id ), 'src_network_delete_' . $mapping->id, 'src_nonce' ) ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'subdomain-redirect-counter' ); ?>');">
											<?php esc_html_e( 'Delete', 'subdomain-redirect-counter' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render mapping form for add/edit.
	 *
	 * @param int         $site_id The site ID.
	 * @param object|null $mapping The mapping object for edit, or null for add.
	 * @return void
	 */
	private function render_mapping_form( int $site_id, ?object $mapping ): void {
		$is_edit = null !== $mapping;
		$action  = $is_edit ? 'src_network_edit_mapping' : 'src_network_add_mapping';
		$nonce   = $is_edit ? 'src_network_edit_' . $mapping->id : 'src_network_add';

		// Get posts for the selected site.
		switch_to_blog( $site_id );
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'posts_per_page' => 100,
				'post_status'    => 'publish',
			)
		);
		restore_current_blog();
		?>
		<div class="src-mapping-form" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px; margin: 20px 0;">
			<h2><?php echo $is_edit ? esc_html__( 'Edit Mapping', 'subdomain-redirect-counter' ) : esc_html__( 'Add New Mapping', 'subdomain-redirect-counter' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
				<input type="hidden" name="site_id" value="<?php echo esc_attr( $site_id ); ?>">
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="mapping_id" value="<?php echo esc_attr( $mapping->id ); ?>">
				<?php endif; ?>
				<?php wp_nonce_field( $nonce, 'src_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="subdomain"><?php esc_html_e( 'Subdomain', 'subdomain-redirect-counter' ); ?></label></th>
						<td>
							<input type="text" name="subdomain" id="subdomain" class="regular-text" value="<?php echo $is_edit ? esc_attr( $mapping->subdomain ) : ''; ?>" required>
							<p class="description"><?php esc_html_e( 'Enter the subdomain (e.g., "tickets" or "tickets.sunspotswifi.com"). The domain part will be stripped automatically.', 'subdomain-redirect-counter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="mapping_type"><?php esc_html_e( 'Mapping Type', 'subdomain-redirect-counter' ); ?></label></th>
						<td>
							<select name="mapping_type" id="mapping_type">
								<option value="post" <?php echo $is_edit ? selected( $mapping->mapping_type, 'post', false ) : ''; ?>><?php esc_html_e( 'Post/Page', 'subdomain-redirect-counter' ); ?></option>
								<option value="url" <?php echo $is_edit ? selected( $mapping->mapping_type, 'url', false ) : ''; ?>><?php esc_html_e( 'Custom URL', 'subdomain-redirect-counter' ); ?></option>
								<option value="home" <?php echo $is_edit ? selected( $mapping->mapping_type, 'home', false ) : ''; ?>><?php esc_html_e( 'Homepage', 'subdomain-redirect-counter' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="src-post-field">
						<th><label for="post_id"><?php esc_html_e( 'Post/Page', 'subdomain-redirect-counter' ); ?></label></th>
						<td>
							<select name="post_id" id="post_id">
								<option value=""><?php esc_html_e( '— Select —', 'subdomain-redirect-counter' ); ?></option>
								<?php foreach ( $posts as $post ) : ?>
									<option value="<?php echo esc_attr( $post->ID ); ?>" <?php echo $is_edit ? selected( $mapping->post_id, $post->ID, false ) : ''; ?>>
										<?php echo esc_html( $post->post_title . ' (' . $post->post_type . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr class="src-url-field" style="display: none;">
						<th><label for="redirect_url"><?php esc_html_e( 'Redirect URL', 'subdomain-redirect-counter' ); ?></label></th>
						<td>
							<input type="url" name="redirect_url" id="redirect_url" class="regular-text" value="<?php echo $is_edit ? esc_attr( $mapping->redirect_url ) : ''; ?>">
						</td>
					</tr>
					<tr>
						<th><label for="redirect_code"><?php esc_html_e( 'Redirect Code', 'subdomain-redirect-counter' ); ?></label></th>
						<td>
							<select name="redirect_code" id="redirect_code">
								<option value="301" <?php echo $is_edit ? selected( $mapping->redirect_code, 301, false ) : ''; ?>>301 (Permanent)</option>
								<option value="302" <?php echo $is_edit ? selected( $mapping->redirect_code, 302, false ) : ''; ?>>302 (Temporary)</option>
								<option value="307" <?php echo $is_edit ? selected( $mapping->redirect_code, 307, false ) : ''; ?>>307 (Temporary, preserve method)</option>
								<option value="308" <?php echo $is_edit ? selected( $mapping->redirect_code, 308, false ) : ''; ?>>308 (Permanent, preserve method)</option>
							</select>
						</td>
					</tr>
					<?php if ( $is_edit ) : ?>
						<tr>
							<th><label for="is_active"><?php esc_html_e( 'Status', 'subdomain-redirect-counter' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" name="is_active" id="is_active" value="1" <?php checked( $mapping->is_active ); ?>>
									<?php esc_html_e( 'Active', 'subdomain-redirect-counter' ); ?>
								</label>
							</td>
						</tr>
					<?php endif; ?>
				</table>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php echo $is_edit ? esc_attr__( 'Update Mapping', 'subdomain-redirect-counter' ) : esc_attr__( 'Add Mapping', 'subdomain-redirect-counter' ); ?>">
					<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=src-network-mappings&site_id=' . $site_id ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'subdomain-redirect-counter' ); ?></a>
				</p>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			function toggleFields() {
				var type = $('#mapping_type').val();
				$('.src-post-field').toggle(type === 'post');
				$('.src-url-field').toggle(type === 'url');
			}
			$('#mapping_type').on('change', toggleFields);
			toggleFields();
		});
		</script>
		<?php
	}

	/**
	 * Render network statistics page.
	 *
	 * @return void
	 */
	public function render_network_statistics(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'subdomain-redirect-counter' ) );
		}

		$stats = $this->get_network_stats();

		// Get top subdomains across network.
		$top_subdomains = array();
		SRC_Database::for_each_site(
			function () use ( &$top_subdomains ) {
				$site_stats = SRC_Statistics::get_all( array( 'limit' => 10 ) );
				foreach ( $site_stats as $stat ) {
					$key = $stat->subdomain;
					if ( ! isset( $top_subdomains[ $key ] ) ) {
						$top_subdomains[ $key ] = 0;
					}
					$top_subdomains[ $key ] += $stat->redirect_count;
				}
			}
		);
		arsort( $top_subdomains );
		$top_subdomains = array_slice( $top_subdomains, 0, 10, true );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Network Statistics', 'subdomain-redirect-counter' ); ?></h1>

			<div class="src-network-stats" style="display: flex; gap: 20px; margin: 20px 0;">
				<div class="src-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1;">
					<h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Total Redirects', 'subdomain-redirect-counter' ); ?></h3>
					<p style="font-size: 36px; margin: 0; color: #0073aa;"><?php echo esc_html( number_format_i18n( $stats->total_redirects ) ); ?></p>
				</div>
				<div class="src-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1;">
					<h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Active Mappings', 'subdomain-redirect-counter' ); ?></h3>
					<p style="font-size: 36px; margin: 0; color: #0073aa;"><?php echo esc_html( number_format_i18n( $stats->total_mappings ) ); ?></p>
				</div>
				<div class="src-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1;">
					<h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Network Sites', 'subdomain-redirect-counter' ); ?></h3>
					<p style="font-size: 36px; margin: 0; color: #0073aa;"><?php echo esc_html( number_format_i18n( $stats->total_sites ) ); ?></p>
				</div>
			</div>

			<div style="display: flex; gap: 20px;">
				<div style="flex: 1;">
					<h2><?php esc_html_e( 'Redirects by Site', 'subdomain-redirect-counter' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Site', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Mappings', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Redirects', 'subdomain-redirect-counter' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stats->sites as $site ) : ?>
								<tr>
									<td><?php echo esc_html( $site->name ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $site->mappings ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $site->redirects ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div style="flex: 1;">
					<h2><?php esc_html_e( 'Top Subdomains (Network-wide)', 'subdomain-redirect-counter' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Subdomain', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Total Redirects', 'subdomain-redirect-counter' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $top_subdomains ) ) : ?>
								<tr>
									<td colspan="2"><?php esc_html_e( 'No data yet.', 'subdomain-redirect-counter' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $top_subdomains as $subdomain => $count ) : ?>
									<tr>
										<td><code><?php echo esc_html( $subdomain ); ?></code></td>
										<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle add mapping action.
	 *
	 * @return void
	 */
	public function handle_add_mapping(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'subdomain-redirect-counter' ) );
		}

		check_admin_referer( 'src_network_add', 'src_nonce' );

		$site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;

		// Validate site exists.
		if ( ! get_site( $site_id ) ) {
			wp_die( esc_html__( 'Invalid site.', 'subdomain-redirect-counter' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by extract_subdomain().
		$subdomain_input = isset( $_POST['subdomain'] ) ? wp_unslash( $_POST['subdomain'] ) : '';
		$subdomain       = $this->extract_subdomain( $subdomain_input );
		$mapping_type    = isset( $_POST['mapping_type'] ) ? sanitize_key( $_POST['mapping_type'] ) : 'post';
		$post_id         = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw provides sanitization.
		$redirect_url    = isset( $_POST['redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) : '';
		$redirect_code   = isset( $_POST['redirect_code'] ) ? absint( $_POST['redirect_code'] ) : 301;

		// Switch to target site and add mapping.
		switch_to_blog( $site_id );
		$result = SRC_Mappings::add( $subdomain, $post_id, $mapping_type, $redirect_url, $redirect_code );
		restore_current_blog();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'src-network-mappings',
					'site_id' => $site_id,
					'message' => $result ? 'added' : 'error',
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle edit mapping action.
	 *
	 * @return void
	 */
	public function handle_edit_mapping(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'subdomain-redirect-counter' ) );
		}

		$mapping_id = isset( $_POST['mapping_id'] ) ? absint( $_POST['mapping_id'] ) : 0;

		check_admin_referer( 'src_network_edit_' . $mapping_id, 'src_nonce' );

		$site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;

		// Validate site exists.
		if ( ! get_site( $site_id ) ) {
			wp_die( esc_html__( 'Invalid site.', 'subdomain-redirect-counter' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by extract_subdomain().
		$subdomain_input = isset( $_POST['subdomain'] ) ? wp_unslash( $_POST['subdomain'] ) : '';
		$subdomain       = $this->extract_subdomain( $subdomain_input );
		$mapping_type    = isset( $_POST['mapping_type'] ) ? sanitize_key( $_POST['mapping_type'] ) : 'post';
		$post_id         = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw provides sanitization.
		$redirect_url    = isset( $_POST['redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) : '';
		$redirect_code   = isset( $_POST['redirect_code'] ) ? absint( $_POST['redirect_code'] ) : 301;
		$is_active       = isset( $_POST['is_active'] );

		// Switch to target site and update mapping.
		switch_to_blog( $site_id );
		$result = SRC_Mappings::update( $mapping_id, $subdomain, $post_id, $mapping_type, $redirect_url, $redirect_code );
		if ( $result ) {
			SRC_Mappings::set_active( $mapping_id, $is_active );
		}
		restore_current_blog();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'src-network-mappings',
					'site_id' => $site_id,
					'message' => $result ? 'updated' : 'error',
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle delete mapping action.
	 *
	 * @return void
	 */
	public function handle_delete_mapping(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'subdomain-redirect-counter' ) );
		}

		$mapping_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		check_admin_referer( 'src_network_delete_' . $mapping_id, 'src_nonce' );

		$site_id = isset( $_GET['site_id'] ) ? absint( $_GET['site_id'] ) : 0;

		// Validate site exists.
		if ( ! get_site( $site_id ) ) {
			wp_die( esc_html__( 'Invalid site.', 'subdomain-redirect-counter' ) );
		}

		// Switch to target site and delete mapping.
		switch_to_blog( $site_id );
		$result = SRC_Mappings::delete( $mapping_id );
		restore_current_blog();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'src-network-mappings',
					'site_id' => $site_id,
					'message' => $result ? 'deleted' : 'error',
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Extract subdomain from input that may be full domain or just subdomain.
	 *
	 * Accepts either:
	 * - Just the subdomain: "tickets"
	 * - Full subdomain.domain: "tickets.example.com"
	 *
	 * @param string $input The user input.
	 * @return string The extracted subdomain.
	 */
	private function extract_subdomain( string $input ): string {
		$input = strtolower( trim( $input ) );

		// If input contains a dot, extract the first part (subdomain).
		if ( strpos( $input, '.' ) !== false ) {
			$parts = explode( '.', $input );
			$input = $parts[0];
		}

		return sanitize_key( $input );
	}
}

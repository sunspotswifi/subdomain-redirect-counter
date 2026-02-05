<?php
/**
 * Admin class.
 *
 * @package Subdomain_Redirect_Counter
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin functionality.
 *
 * @since 1.0.0
 */
class SRC_Admin {

	/**
	 * Single instance of the class.
	 *
	 * @var SRC_Admin|null
	 */
	private static ?SRC_Admin $instance = null;

	/**
	 * Get the single instance.
	 *
	 * @return SRC_Admin
	 */
	public static function get_instance(): SRC_Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_src_reset_stats', array( $this, 'handle_reset_stats' ) );
		add_action( 'admin_post_src_clear_logs', array( $this, 'handle_clear_logs' ) );
		add_action( 'admin_post_src_add_mapping', array( $this, 'handle_add_mapping' ) );
		add_action( 'admin_post_src_edit_mapping', array( $this, 'handle_edit_mapping' ) );
		add_action( 'admin_post_src_delete_mapping', array( $this, 'handle_delete_mapping' ) );
	}

	/**
	 * Add admin menu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'Subdomain Redirects', 'subdomain-redirect-counter' ),
			__( 'Subdomain Redirects', 'subdomain-redirect-counter' ),
			'manage_options',
			'subdomain-redirect-counter',
			array( $this, 'render_dashboard_page' ),
			'dashicons-randomize',
			30
		);

		add_submenu_page(
			'subdomain-redirect-counter',
			__( 'Dashboard', 'subdomain-redirect-counter' ),
			__( 'Dashboard', 'subdomain-redirect-counter' ),
			'manage_options',
			'subdomain-redirect-counter',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'subdomain-redirect-counter',
			__( 'Mappings', 'subdomain-redirect-counter' ),
			__( 'Mappings', 'subdomain-redirect-counter' ),
			'manage_options',
			'subdomain-redirect-counter-mappings',
			array( $this, 'render_mappings_page' )
		);

		add_submenu_page(
			'subdomain-redirect-counter',
			__( 'Statistics', 'subdomain-redirect-counter' ),
			__( 'Statistics', 'subdomain-redirect-counter' ),
			'manage_options',
			'subdomain-redirect-counter-stats',
			array( $this, 'render_statistics_page' )
		);

		add_submenu_page(
			'subdomain-redirect-counter',
			__( 'Logs', 'subdomain-redirect-counter' ),
			__( 'Logs', 'subdomain-redirect-counter' ),
			'manage_options',
			'subdomain-redirect-counter-logs',
			array( $this, 'render_logs_page' )
		);

		add_submenu_page(
			'subdomain-redirect-counter',
			__( 'Settings', 'subdomain-redirect-counter' ),
			__( 'Settings', 'subdomain-redirect-counter' ),
			'manage_options',
			'subdomain-redirect-counter-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'src_settings_group',
			'src_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'enabled'             => true,
					'logging_enabled'     => false,
					'excluded_subdomains' => array( 'www', 'mail', 'ftp', 'cpanel', 'webmail' ),
					'aliased_domains'     => array(),
					'log_retention_days'  => 0,
				),
			)
		);

		add_settings_section(
			'src_general_section',
			__( 'General Settings', 'subdomain-redirect-counter' ),
			array( $this, 'render_general_section' ),
			'subdomain-redirect-counter-settings'
		);

		add_settings_field(
			'src_enabled',
			__( 'Enable Subdomain Interception', 'subdomain-redirect-counter' ),
			array( $this, 'render_enabled_field' ),
			'subdomain-redirect-counter-settings',
			'src_general_section'
		);

		add_settings_field(
			'src_logging_enabled',
			__( 'Enable Logging', 'subdomain-redirect-counter' ),
			array( $this, 'render_logging_field' ),
			'subdomain-redirect-counter-settings',
			'src_general_section'
		);

		add_settings_field(
			'src_excluded_subdomains',
			__( 'Excluded Subdomains', 'subdomain-redirect-counter' ),
			array( $this, 'render_excluded_field' ),
			'subdomain-redirect-counter-settings',
			'src_general_section'
		);

		add_settings_field(
			'src_aliased_domains',
			__( 'Aliased Domains', 'subdomain-redirect-counter' ),
			array( $this, 'render_aliased_domains_field' ),
			'subdomain-redirect-counter-settings',
			'src_general_section'
		);

		add_settings_field(
			'src_log_retention',
			__( 'Log Retention', 'subdomain-redirect-counter' ),
			array( $this, 'render_log_retention_field' ),
			'subdomain-redirect-counter-settings',
			'src_general_section'
		);

		add_settings_field(
			'src_unmapped_behavior',
			__( 'Unmapped Subdomains', 'subdomain-redirect-counter' ),
			array( $this, 'render_unmapped_behavior_field' ),
			'subdomain-redirect-counter-settings',
			'src_general_section'
		);

		add_settings_field(
			'src_redirect_domains',
			__( 'Domain Redirects', 'subdomain-redirect-counter' ),
			array( $this, 'render_redirect_domains_field' ),
			'subdomain-redirect-counter-settings',
			'src_general_section'
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input The input values.
	 * @return array Sanitized values.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		$sanitized['enabled']         = ! empty( $input['enabled'] );
		$sanitized['logging_enabled'] = ! empty( $input['logging_enabled'] );

		// Sanitize excluded subdomains.
		$excluded = isset( $input['excluded_subdomains'] ) ? $input['excluded_subdomains'] : '';
		if ( is_string( $excluded ) ) {
			$excluded = array_map( 'trim', explode( ',', $excluded ) );
		}
		$sanitized['excluded_subdomains'] = array_filter(
			array_map( 'sanitize_key', $excluded )
		);

		// Sanitize log retention days.
		$retention_days                    = isset( $input['log_retention_days'] ) ? absint( $input['log_retention_days'] ) : 0;
		$valid_retention                   = array( 0, 7, 14, 30, 60, 90, 180, 365 );
		$sanitized['log_retention_days']   = in_array( $retention_days, $valid_retention, true ) ? $retention_days : 0;

		// Sanitize unmapped behavior.
		$unmapped_behavior                    = isset( $input['unmapped_behavior'] ) ? sanitize_key( $input['unmapped_behavior'] ) : 'show';
		$sanitized['unmapped_behavior']       = in_array( $unmapped_behavior, array( 'show', 'redirect' ), true ) ? $unmapped_behavior : 'show';

		// Sanitize unmapped redirect code.
		$unmapped_redirect_code               = isset( $input['unmapped_redirect_code'] ) ? absint( $input['unmapped_redirect_code'] ) : 302;
		$valid_codes                          = array( 301, 302, 307, 308 );
		$sanitized['unmapped_redirect_code']  = in_array( $unmapped_redirect_code, $valid_codes, true ) ? $unmapped_redirect_code : 302;

		// Sanitize aliased domains.
		$aliased = isset( $input['aliased_domains'] ) ? $input['aliased_domains'] : '';
		if ( is_string( $aliased ) ) {
			$aliased = array_map( 'trim', explode( ',', $aliased ) );
		}
		$sanitized['aliased_domains'] = array_filter(
			array_map(
				function ( $domain ) {
					// Remove protocol and trailing slashes.
					$domain = preg_replace( '#^https?://#', '', $domain );
					$domain = rtrim( $domain, '/' );
					// Remove www prefix.
					$domain = preg_replace( '/^www\./i', '', $domain );
					// Sanitize as text field (domains can have hyphens and dots).
					return sanitize_text_field( strtolower( $domain ) );
				},
				$aliased
			)
		);

		// Sanitize redirect domains.
		$redirect_domains = array();
		if ( isset( $input['redirect_domains'] ) && is_array( $input['redirect_domains'] ) ) {
			foreach ( $input['redirect_domains'] as $redirect ) {
				if ( empty( $redirect['domain'] ) || empty( $redirect['target_url'] ) ) {
					continue;
				}

				// Clean domain.
				$domain = preg_replace( '#^https?://#', '', $redirect['domain'] );
				$domain = rtrim( $domain, '/' );
				$domain = preg_replace( '/^www\./i', '', $domain );
				$domain = sanitize_text_field( strtolower( $domain ) );

				// Clean target URL.
				$target_url = esc_url_raw( $redirect['target_url'] );

				// Validate redirect code.
				$redirect_code = isset( $redirect['redirect_code'] ) ? absint( $redirect['redirect_code'] ) : 301;
				if ( ! in_array( $redirect_code, $valid_codes, true ) ) {
					$redirect_code = 301;
				}

				if ( ! empty( $domain ) && ! empty( $target_url ) ) {
					$redirect_domains[] = array(
						'domain'        => $domain,
						'target_url'    => $target_url,
						'redirect_code' => $redirect_code,
					);
				}
			}
		}
		$sanitized['redirect_domains'] = $redirect_domains;

		return $sanitized;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on our plugin pages.
		if ( strpos( $hook, 'subdomain-redirect-counter' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'src-admin-styles',
			SRC_PLUGIN_URL . 'admin/css/admin-styles.css',
			array(),
			SRC_VERSION
		);
	}

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		$summary = SRC_Statistics::get_summary();
		$top     = SRC_Statistics::get_top_subdomains( 5 );
		$recent  = SRC_Statistics::get_recent( 5 );
		?>
		<div class="wrap src-admin-wrap">
			<h1><?php esc_html_e( 'Subdomain Redirect Counter', 'subdomain-redirect-counter' ); ?></h1>

			<div class="src-dashboard-grid">
				<div class="src-card src-summary-card">
					<h2><?php esc_html_e( 'Overview', 'subdomain-redirect-counter' ); ?></h2>
					<div class="src-stats-grid">
						<div class="src-stat-item">
							<span class="src-stat-value"><?php echo esc_html( number_format_i18n( $summary['total_subdomains'] ) ); ?></span>
							<span class="src-stat-label"><?php esc_html_e( 'Unique Subdomains', 'subdomain-redirect-counter' ); ?></span>
						</div>
						<div class="src-stat-item">
							<span class="src-stat-value"><?php echo esc_html( number_format_i18n( $summary['total_redirects'] ) ); ?></span>
							<span class="src-stat-label"><?php esc_html_e( 'Total Redirects', 'subdomain-redirect-counter' ); ?></span>
						</div>
						<div class="src-stat-item">
							<span class="src-stat-value">
								<?php
								if ( $summary['last_redirect_time'] ) {
									$last_redirect_dt = new DateTime( $summary['last_redirect_time'], wp_timezone() );
									echo esc_html(
										sprintf(
											/* translators: %s: Human-readable time difference */
											__( '%s ago', 'subdomain-redirect-counter' ),
											human_time_diff( $last_redirect_dt->getTimestamp() )
										)
									);
								} else {
									esc_html_e( 'Never', 'subdomain-redirect-counter' );
								}
								?>
							</span>
							<span class="src-stat-label"><?php esc_html_e( 'Last Redirect', 'subdomain-redirect-counter' ); ?></span>
						</div>
					</div>
				</div>

				<div class="src-card">
					<h2><?php esc_html_e( 'Top Subdomains', 'subdomain-redirect-counter' ); ?></h2>
					<?php if ( ! empty( $top ) ) : ?>
						<table class="src-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Subdomain', 'subdomain-redirect-counter' ); ?></th>
									<th><?php esc_html_e( 'Redirects', 'subdomain-redirect-counter' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $top as $item ) : ?>
									<tr>
										<td><code><?php echo esc_html( $item->subdomain ); ?></code></td>
										<td><?php echo esc_html( number_format_i18n( $item->redirect_count ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="src-no-data"><?php esc_html_e( 'No redirect data yet.', 'subdomain-redirect-counter' ); ?></p>
					<?php endif; ?>
					<p class="src-view-all">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=subdomain-redirect-counter-stats' ) ); ?>">
							<?php esc_html_e( 'View All Statistics', 'subdomain-redirect-counter' ); ?> &rarr;
						</a>
					</p>
				</div>

				<div class="src-card">
					<h2><?php esc_html_e( 'Recent Activity', 'subdomain-redirect-counter' ); ?></h2>
					<?php if ( ! empty( $recent ) ) : ?>
						<table class="src-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Subdomain', 'subdomain-redirect-counter' ); ?></th>
									<th><?php esc_html_e( 'Last Redirect', 'subdomain-redirect-counter' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent as $item ) : ?>
									<tr>
										<td><code><?php echo esc_html( $item->subdomain ); ?></code></td>
										<td>
											<?php
											$item_last_redirect_dt = new DateTime( $item->last_redirect, wp_timezone() );
											echo esc_html(
												sprintf(
													/* translators: %s: Human-readable time difference */
													__( '%s ago', 'subdomain-redirect-counter' ),
													human_time_diff( $item_last_redirect_dt->getTimestamp() )
												)
											);
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="src-no-data"><?php esc_html_e( 'No recent activity.', 'subdomain-redirect-counter' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="src-card src-quick-info">
					<h2><?php esc_html_e( 'How It Works', 'subdomain-redirect-counter' ); ?></h2>
					<p><?php esc_html_e( 'This plugin intercepts requests to subdomains of your WordPress domain and converts them to local permalinks.', 'subdomain-redirect-counter' ); ?></p>
					<p><strong><?php esc_html_e( 'Example:', 'subdomain-redirect-counter' ); ?></strong></p>
					<p>
						<code>tickets.<?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></code>
						&rarr;
						<code><?php echo esc_url( home_url( '/tickets/' ) ); ?></code>
					</p>
					<p class="src-view-all">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=subdomain-redirect-counter-settings' ) ); ?>">
							<?php esc_html_e( 'Configure Settings', 'subdomain-redirect-counter' ); ?> &rarr;
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the statistics page.
	 *
	 * @return void
	 */
	public function render_statistics_page(): void {
		$paged  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$limit  = 20;
		$offset = ( $paged - 1 ) * $limit;

		$stats = SRC_Statistics::get_all(
			array(
				'limit'  => $limit,
				'offset' => $offset,
			)
		);
		$total = SRC_Statistics::get_total_count();
		$pages = ceil( $total / $limit );
		?>
		<div class="wrap src-admin-wrap">
			<h1><?php esc_html_e( 'Redirect Statistics', 'subdomain-redirect-counter' ); ?></h1>

			<div class="src-card">
				<div class="src-card-header">
					<h2><?php esc_html_e( 'All Subdomains', 'subdomain-redirect-counter' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="src-inline-form">
						<?php wp_nonce_field( 'src_reset_stats', 'src_nonce' ); ?>
						<input type="hidden" name="action" value="src_reset_stats">
						<button type="submit" class="button" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to reset all statistics?', 'subdomain-redirect-counter' ); ?>');">
							<?php esc_html_e( 'Reset All Statistics', 'subdomain-redirect-counter' ); ?>
						</button>
					</form>
				</div>

				<?php if ( ! empty( $stats ) ) : ?>
					<table class="src-table src-table-full">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Subdomain', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Target Path', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Redirect Count', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Last Redirect', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'First Seen', 'subdomain-redirect-counter' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stats as $item ) : ?>
								<tr>
									<td><code><?php echo esc_html( $item->subdomain ); ?></code></td>
									<td><code><?php echo esc_html( $item->target_path ); ?></code></td>
									<td class="src-count"><?php echo esc_html( number_format_i18n( $item->redirect_count ) ); ?></td>
									<td>
										<?php
										echo esc_html(
											wp_date(
												get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
												strtotime( $item->last_redirect )
											)
										);
										?>
									</td>
									<td>
										<?php
										echo esc_html(
											wp_date(
												get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
												strtotime( $item->created_at )
											)
										);
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $pages > 1 ) : ?>
						<div class="src-pagination">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%' ),
										'format'    => '',
										'prev_text' => '&laquo; ' . __( 'Previous', 'subdomain-redirect-counter' ),
										'next_text' => __( 'Next', 'subdomain-redirect-counter' ) . ' &raquo;',
										'total'     => $pages,
										'current'   => $paged,
									)
								)
							);
							?>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<p class="src-no-data"><?php esc_html_e( 'No statistics recorded yet.', 'subdomain-redirect-counter' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the logs page.
	 *
	 * @return void
	 */
	public function render_logs_page(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'view' === $action && $id > 0 ) {
			$this->render_log_detail( $id );
			return;
		}

		$settings    = get_option( 'src_settings', array() );
		$paged       = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$limit       = 20;
		$offset      = ( $paged - 1 ) * $limit;

		// Build query args with optional type filter.
		$query_args = array(
			'limit'  => $limit,
			'offset' => $offset,
		);

		// Filter by type (domain redirects use @ prefix).
		if ( 'domain' === $filter_type ) {
			$query_args['subdomain_like'] = '@%';
		} elseif ( 'subdomain' === $filter_type ) {
			$query_args['subdomain_not_like'] = '@%';
		}

		$logs  = SRC_Logger::get_logs( $query_args );
		$total = SRC_Logger::get_total_count( $query_args );
		$pages = ceil( $total / $limit );
		?>
		<div class="wrap src-admin-wrap">
			<h1><?php esc_html_e( 'Redirect Logs', 'subdomain-redirect-counter' ); ?></h1>

			<?php if ( empty( $settings['logging_enabled'] ) ) : ?>
				<div class="src-notice src-notice-warning">
					<p>
						<?php esc_html_e( 'Logging is currently disabled.', 'subdomain-redirect-counter' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=subdomain-redirect-counter-settings' ) ); ?>">
							<?php esc_html_e( 'Enable logging in settings', 'subdomain-redirect-counter' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<div class="src-card">
				<div class="src-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
					<h2 style="margin: 0;"><?php esc_html_e( 'Redirect Logs', 'subdomain-redirect-counter' ); ?></h2>
					<div style="display: flex; gap: 10px; align-items: center;">
						<form method="get" style="display: flex; gap: 5px; align-items: center;">
							<input type="hidden" name="page" value="subdomain-redirect-counter-logs">
							<label for="type-filter"><?php esc_html_e( 'Type:', 'subdomain-redirect-counter' ); ?></label>
							<select name="type" id="type-filter" onchange="this.form.submit()">
								<option value="all" <?php selected( $filter_type, 'all' ); ?>><?php esc_html_e( 'All', 'subdomain-redirect-counter' ); ?></option>
								<option value="subdomain" <?php selected( $filter_type, 'subdomain' ); ?>><?php esc_html_e( 'Subdomains', 'subdomain-redirect-counter' ); ?></option>
								<option value="domain" <?php selected( $filter_type, 'domain' ); ?>><?php esc_html_e( 'Domain Redirects', 'subdomain-redirect-counter' ); ?></option>
							</select>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="src-inline-form">
							<?php wp_nonce_field( 'src_clear_logs', 'src_nonce' ); ?>
							<input type="hidden" name="action" value="src_clear_logs">
							<button type="submit" class="button" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all logs?', 'subdomain-redirect-counter' ); ?>');">
								<?php esc_html_e( 'Clear All Logs', 'subdomain-redirect-counter' ); ?>
							</button>
						</form>
					</div>
				</div>

				<?php if ( ! empty( $logs ) ) : ?>
					<table class="src-table src-table-full">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date/Time', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Source', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Target', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'IP Address', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'User Agent', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'subdomain-redirect-counter' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<?php
								$is_domain_redirect = strpos( $log->subdomain, '@' ) === 0;
								$display_source     = $is_domain_redirect ? substr( $log->subdomain, 1 ) : $log->subdomain;
								?>
								<tr>
									<td>
										<?php
										echo esc_html(
											wp_date(
												get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
												strtotime( $log->created_at )
											)
										);
										?>
									</td>
									<td>
										<?php if ( $is_domain_redirect ) : ?>
											<span class="src-badge src-badge-domain" style="background: #0073aa; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-right: 5px;"><?php esc_html_e( 'Domain', 'subdomain-redirect-counter' ); ?></span>
										<?php endif; ?>
										<code><?php echo esc_html( $display_source ); ?></code>
									</td>
									<td><code><?php echo esc_html( $log->target_path ); ?></code></td>
									<td><?php echo esc_html( $log->ip_address ? $log->ip_address : '-' ); ?></td>
									<td class="src-ua-cell" title="<?php echo esc_attr( $log->user_agent ); ?>">
										<?php echo esc_html( wp_trim_words( $log->user_agent ? $log->user_agent : '-', 10, '...' ) ); ?>
									</td>
									<td class="src-actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=subdomain-redirect-counter-logs&action=view&id=' . $log->id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', 'subdomain-redirect-counter' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $pages > 1 ) : ?>
						<div class="src-pagination">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%' ),
										'format'    => '',
										'prev_text' => '&laquo; ' . __( 'Previous', 'subdomain-redirect-counter' ),
										'next_text' => __( 'Next', 'subdomain-redirect-counter' ) . ' &raquo;',
										'total'     => $pages,
										'current'   => $paged,
									)
								)
							);
							?>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<p class="src-no-data"><?php esc_html_e( 'No logs recorded yet.', 'subdomain-redirect-counter' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single log entry detail view.
	 *
	 * @param int $id The log entry ID.
	 * @return void
	 */
	private function render_log_detail( int $id ): void {
		$log = SRC_Logger::get_log_by_id( $id );

		if ( ! $log ) {
			?>
			<div class="wrap src-admin-wrap">
				<h1><?php esc_html_e( 'Log Entry Not Found', 'subdomain-redirect-counter' ); ?></h1>
				<div class="src-notice src-notice-error">
					<p><?php esc_html_e( 'The requested log entry could not be found.', 'subdomain-redirect-counter' ); ?></p>
				</div>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=subdomain-redirect-counter-logs' ) ); ?>" class="button">
						&larr; <?php esc_html_e( 'Back to Logs', 'subdomain-redirect-counter' ); ?>
					</a>
				</p>
			</div>
			<?php
			return;
		}
		?>
		<div class="wrap src-admin-wrap">
			<h1><?php esc_html_e( 'Log Entry Details', 'subdomain-redirect-counter' ); ?></h1>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=subdomain-redirect-counter-logs' ) ); ?>" class="button">
					&larr; <?php esc_html_e( 'Back to Logs', 'subdomain-redirect-counter' ); ?>
				</a>
			</p>

			<div class="src-card">
				<h2><?php esc_html_e( 'Redirect Information', 'subdomain-redirect-counter' ); ?></h2>

				<table class="src-detail-table">
					<tr>
						<th><?php esc_html_e( 'Log ID', 'subdomain-redirect-counter' ); ?></th>
						<td><?php echo esc_html( $log->id ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Date/Time', 'subdomain-redirect-counter' ); ?></th>
						<td>
							<?php
							echo esc_html(
								wp_date(
									get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
									strtotime( $log->created_at )
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Subdomain', 'subdomain-redirect-counter' ); ?></th>
						<td><code><?php echo esc_html( $log->subdomain ); ?></code></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Target Path', 'subdomain-redirect-counter' ); ?></th>
						<td><code><?php echo esc_html( $log->target_path ); ?></code></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Source URL', 'subdomain-redirect-counter' ); ?></th>
						<td>
							<?php if ( ! empty( $log->source_url ) ) : ?>
								<code class="src-detail-url"><?php echo esc_html( $log->source_url ); ?></code>
							<?php else : ?>
								<span class="src-detail-empty"><?php esc_html_e( '(not recorded)', 'subdomain-redirect-counter' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<div class="src-card">
				<h2><?php esc_html_e( 'Visitor Information', 'subdomain-redirect-counter' ); ?></h2>

				<table class="src-detail-table">
					<tr>
						<th><?php esc_html_e( 'IP Address', 'subdomain-redirect-counter' ); ?></th>
						<td>
							<?php if ( ! empty( $log->ip_address ) ) : ?>
								<?php echo esc_html( $log->ip_address ); ?>
								<span class="src-detail-note"><?php esc_html_e( '(anonymized)', 'subdomain-redirect-counter' ); ?></span>
							<?php else : ?>
								<span class="src-detail-empty"><?php esc_html_e( '(not recorded)', 'subdomain-redirect-counter' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Referer', 'subdomain-redirect-counter' ); ?></th>
						<td>
							<?php if ( ! empty( $log->referer ) ) : ?>
								<code class="src-detail-url"><?php echo esc_html( $log->referer ); ?></code>
							<?php else : ?>
								<span class="src-detail-empty"><?php esc_html_e( '(direct visit or not recorded)', 'subdomain-redirect-counter' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'User Agent', 'subdomain-redirect-counter' ); ?></th>
						<td>
							<?php if ( ! empty( $log->user_agent ) ) : ?>
								<code class="src-detail-ua"><?php echo esc_html( $log->user_agent ); ?></code>
							<?php else : ?>
								<span class="src-detail-empty"><?php esc_html_e( '(not recorded)', 'subdomain-redirect-counter' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$settings_updated = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap src-admin-wrap">
			<h1><?php esc_html_e( 'Settings', 'subdomain-redirect-counter' ); ?></h1>

			<?php if ( $settings_updated ) : ?>
				<div class="src-notice src-notice-success src-notice-dismissible" id="src-settings-saved-notice">
					<p><?php esc_html_e( 'Settings saved successfully.', 'subdomain-redirect-counter' ); ?></p>
					<button type="button" class="src-notice-dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'subdomain-redirect-counter' ); ?>">
						<span class="dashicons dashicons-dismiss"></span>
					</button>
				</div>
				<script>
				(function() {
					var notice = document.getElementById('src-settings-saved-notice');
					var dismissBtn = notice.querySelector('.src-notice-dismiss');
					dismissBtn.addEventListener('click', function() {
						notice.style.display = 'none';
						// Remove the query parameter from URL without reload
						var url = new URL(window.location);
						url.searchParams.delete('settings-updated');
						window.history.replaceState({}, '', url);
					});
				})();
				</script>
			<?php endif; ?>

			<div class="src-card">
				<form method="post" action="options.php">
					<?php
					settings_fields( 'src_settings_group' );
					do_settings_sections( 'subdomain-redirect-counter-settings' );
					submit_button();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the general settings section description.
	 *
	 * @return void
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure how subdomain redirection and logging behaves.', 'subdomain-redirect-counter' ) . '</p>';
	}

	/**
	 * Render the enabled field.
	 *
	 * @return void
	 */
	public function render_enabled_field(): void {
		$settings = get_option( 'src_settings', array() );
		$enabled  = $settings['enabled'] ?? true;
		?>
		<label>
			<input type="checkbox" name="src_settings[enabled]" value="1" <?php checked( $enabled ); ?>>
			<?php esc_html_e( 'Enable subdomain interception and redirection', 'subdomain-redirect-counter' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, requests to subdomains will be intercepted and redirected to matching permalinks.', 'subdomain-redirect-counter' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the logging field.
	 *
	 * @return void
	 */
	public function render_logging_field(): void {
		$settings = get_option( 'src_settings', array() );
		$enabled  = $settings['logging_enabled'] ?? false;
		?>
		<label>
			<input type="checkbox" name="src_settings[logging_enabled]" value="1" <?php checked( $enabled ); ?>>
			<?php esc_html_e( 'Enable detailed logging of redirects', 'subdomain-redirect-counter' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, each redirect will be logged with details like IP address and user agent. IP addresses are anonymized for privacy.', 'subdomain-redirect-counter' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the excluded subdomains field.
	 *
	 * @return void
	 */
	public function render_excluded_field(): void {
		$settings = get_option( 'src_settings', array() );
		$excluded = $settings['excluded_subdomains'] ?? array( 'www', 'mail', 'ftp', 'cpanel', 'webmail' );
		$value    = implode( ', ', $excluded );
		?>
		<input type="text" name="src_settings[excluded_subdomains]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Comma-separated list of subdomains to exclude from interception (e.g., www, mail, ftp).', 'subdomain-redirect-counter' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the aliased domains field.
	 *
	 * @return void
	 */
	public function render_aliased_domains_field(): void {
		$settings = get_option( 'src_settings', array() );
		$aliased  = $settings['aliased_domains'] ?? array();
		$value    = implode( ', ', $aliased );
		?>
		<input type="text" name="src_settings[aliased_domains]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Enter the full domain names (e.g., sunspotswifi.com, anotherdomain.com). The plugin will then intercept subdomains of these domains (e.g., tickets.sunspotswifi.com).', 'subdomain-redirect-counter' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the redirect domains field.
	 *
	 * @return void
	 */
	public function render_redirect_domains_field(): void {
		$settings          = get_option( 'src_settings', array() );
		$redirect_domains  = $settings['redirect_domains'] ?? array();

		// Ensure we have at least one empty row for adding.
		if ( empty( $redirect_domains ) ) {
			$redirect_domains = array(
				array(
					'domain'        => '',
					'target_url'    => '',
					'redirect_code' => 301,
				),
			);
		}
		?>
		<div id="src-redirect-domains">
			<p class="description" style="margin-bottom: 10px;">
				<?php esc_html_e( 'Redirect entire domains to another URL. Statistics and logging are recorded for these redirects.', 'subdomain-redirect-counter' ); ?>
			</p>
			<?php if ( is_multisite() && ( ! defined( 'SUNRISE' ) || ! SUNRISE ) ) : ?>
				<div class="notice notice-warning inline" style="margin: 10px 0; max-width: 800px;">
					<p>
						<strong><?php esc_html_e( 'Multisite Notice:', 'subdomain-redirect-counter' ); ?></strong>
						<?php esc_html_e( 'For domain redirects to work on multisite, you need to install sunrise.php:', 'subdomain-redirect-counter' ); ?>
					</p>
					<ol style="margin: 5px 0 5px 20px;">
						<li><?php esc_html_e( 'Copy', 'subdomain-redirect-counter' ); ?> <code><?php echo esc_html( SRC_PLUGIN_DIR . 'sunrise.php' ); ?></code> <?php esc_html_e( 'to', 'subdomain-redirect-counter' ); ?> <code>wp-content/sunrise.php</code></li>
						<li><?php esc_html_e( 'Add', 'subdomain-redirect-counter' ); ?> <code>define( 'SUNRISE', true );</code> <?php esc_html_e( 'to wp-config.php', 'subdomain-redirect-counter' ); ?></li>
					</ol>
				</div>
			<?php endif; ?>
			<table class="widefat" style="max-width: 1000px;">
				<thead>
					<tr>
						<th style="width: 22%;"><?php esc_html_e( 'From Domain', 'subdomain-redirect-counter' ); ?></th>
						<th style="width: 30%;"><?php esc_html_e( 'To URL', 'subdomain-redirect-counter' ); ?></th>
						<th style="width: 10%;"><?php esc_html_e( 'Code', 'subdomain-redirect-counter' ); ?></th>
						<th style="width: 12%; text-align: center;"><?php esc_html_e( 'Keep Path', 'subdomain-redirect-counter' ); ?></th>
						<th style="width: 12%; text-align: center;"><?php esc_html_e( 'Keep Query', 'subdomain-redirect-counter' ); ?></th>
						<th style="width: 8%;"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $redirect_domains as $index => $redirect ) : ?>
						<tr class="src-redirect-row">
							<td>
								<input type="text" name="src_settings[redirect_domains][<?php echo esc_attr( $index ); ?>][domain]" value="<?php echo esc_attr( $redirect['domain'] ?? '' ); ?>" placeholder="example.org" class="regular-text" style="width: 100%;">
							</td>
							<td>
								<input type="url" name="src_settings[redirect_domains][<?php echo esc_attr( $index ); ?>][target_url]" value="<?php echo esc_attr( $redirect['target_url'] ?? '' ); ?>" placeholder="https://example.com" class="regular-text" style="width: 100%;">
							</td>
							<td>
								<select name="src_settings[redirect_domains][<?php echo esc_attr( $index ); ?>][redirect_code]" style="width: 100%;">
									<option value="301" <?php selected( $redirect['redirect_code'] ?? 301, 301 ); ?>>301</option>
									<option value="302" <?php selected( $redirect['redirect_code'] ?? 301, 302 ); ?>>302</option>
									<option value="307" <?php selected( $redirect['redirect_code'] ?? 301, 307 ); ?>>307</option>
									<option value="308" <?php selected( $redirect['redirect_code'] ?? 301, 308 ); ?>>308</option>
								</select>
							</td>
							<td style="text-align: center;">
								<input type="checkbox" name="src_settings[redirect_domains][<?php echo esc_attr( $index ); ?>][keep_path]" value="1" <?php checked( ! empty( $redirect['keep_path'] ) ); ?>>
							</td>
							<td style="text-align: center;">
								<input type="checkbox" name="src_settings[redirect_domains][<?php echo esc_attr( $index ); ?>][keep_query]" value="1" <?php checked( ! empty( $redirect['keep_query'] ) ); ?>>
							</td>
							<td>
								<button type="button" class="button src-remove-redirect" title="<?php esc_attr_e( 'Remove', 'subdomain-redirect-counter' ); ?>">&times;</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top: 10px;">
				<button type="button" class="button" id="src-add-redirect"><?php esc_html_e( 'Add Domain Redirect', 'subdomain-redirect-counter' ); ?></button>
			</p>
		</div>
		<script>
		jQuery(function($) {
			var index = <?php echo count( $redirect_domains ); ?>;

			$('#src-add-redirect').on('click', function() {
				var row = '<tr class="src-redirect-row">' +
					'<td><input type="text" name="src_settings[redirect_domains][' + index + '][domain]" value="" placeholder="example.org" class="regular-text" style="width: 100%;"></td>' +
					'<td><input type="url" name="src_settings[redirect_domains][' + index + '][target_url]" value="" placeholder="https://example.com" class="regular-text" style="width: 100%;"></td>' +
					'<td><select name="src_settings[redirect_domains][' + index + '][redirect_code]" style="width: 100%;"><option value="301">301</option><option value="302">302</option><option value="307">307</option><option value="308">308</option></select></td>' +
					'<td style="text-align: center;"><input type="checkbox" name="src_settings[redirect_domains][' + index + '][keep_path]" value="1" checked></td>' +
					'<td style="text-align: center;"><input type="checkbox" name="src_settings[redirect_domains][' + index + '][keep_query]" value="1" checked></td>' +
					'<td><button type="button" class="button src-remove-redirect" title="<?php esc_attr_e( 'Remove', 'subdomain-redirect-counter' ); ?>">&times;</button></td>' +
					'</tr>';
				$('#src-redirect-domains tbody').append(row);
				index++;
			});

			$(document).on('click', '.src-remove-redirect', function() {
				$(this).closest('tr').remove();
			});
		});
		</script>
		<?php
	}

	/**
	 * Render the log retention field.
	 *
	 * @return void
	 */
	public function render_log_retention_field(): void {
		$settings       = get_option( 'src_settings', array() );
		$retention_days = $settings['log_retention_days'] ?? 0;
		?>
		<select name="src_settings[log_retention_days]" id="src_log_retention_days">
			<option value="0" <?php selected( $retention_days, 0 ); ?>><?php esc_html_e( 'Keep forever (no auto-cleanup)', 'subdomain-redirect-counter' ); ?></option>
			<option value="7" <?php selected( $retention_days, 7 ); ?>><?php esc_html_e( '7 days', 'subdomain-redirect-counter' ); ?></option>
			<option value="14" <?php selected( $retention_days, 14 ); ?>><?php esc_html_e( '14 days', 'subdomain-redirect-counter' ); ?></option>
			<option value="30" <?php selected( $retention_days, 30 ); ?>><?php esc_html_e( '30 days', 'subdomain-redirect-counter' ); ?></option>
			<option value="60" <?php selected( $retention_days, 60 ); ?>><?php esc_html_e( '60 days', 'subdomain-redirect-counter' ); ?></option>
			<option value="90" <?php selected( $retention_days, 90 ); ?>><?php esc_html_e( '90 days', 'subdomain-redirect-counter' ); ?></option>
			<option value="180" <?php selected( $retention_days, 180 ); ?>><?php esc_html_e( '180 days', 'subdomain-redirect-counter' ); ?></option>
			<option value="365" <?php selected( $retention_days, 365 ); ?>><?php esc_html_e( '1 year', 'subdomain-redirect-counter' ); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Automatically delete log entries older than this period. Runs daily via WordPress cron.', 'subdomain-redirect-counter' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the unmapped behavior field.
	 *
	 * @return void
	 */
	public function render_unmapped_behavior_field(): void {
		$settings      = get_option( 'src_settings', array() );
		$behavior      = $settings['unmapped_behavior'] ?? 'show';
		$redirect_code = $settings['unmapped_redirect_code'] ?? 302;
		?>
		<fieldset>
			<label>
				<input type="radio" name="src_settings[unmapped_behavior]" value="show" <?php checked( $behavior, 'show' ); ?> class="src-unmapped-behavior-radio">
				<?php esc_html_e( 'Show homepage content (no redirect)', 'subdomain-redirect-counter' ); ?>
			</label>
			<br>
			<label>
				<input type="radio" name="src_settings[unmapped_behavior]" value="redirect" <?php checked( $behavior, 'redirect' ); ?> class="src-unmapped-behavior-radio">
				<?php esc_html_e( 'Redirect to homepage', 'subdomain-redirect-counter' ); ?>
			</label>
		</fieldset>
		<div class="src-unmapped-redirect-code" style="margin-top: 10px; <?php echo 'show' === $behavior ? 'display:none;' : ''; ?>">
			<label for="src_unmapped_redirect_code"><?php esc_html_e( 'HTTP Status Code:', 'subdomain-redirect-counter' ); ?></label>
			<select name="src_settings[unmapped_redirect_code]" id="src_unmapped_redirect_code">
				<option value="301" <?php selected( $redirect_code, 301 ); ?>><?php esc_html_e( '301 - Permanent', 'subdomain-redirect-counter' ); ?></option>
				<option value="302" <?php selected( $redirect_code, 302 ); ?>><?php esc_html_e( '302 - Temporary (Recommended)', 'subdomain-redirect-counter' ); ?></option>
				<option value="307" <?php selected( $redirect_code, 307 ); ?>><?php esc_html_e( '307 - Temporary (Strict)', 'subdomain-redirect-counter' ); ?></option>
				<option value="308" <?php selected( $redirect_code, 308 ); ?>><?php esc_html_e( '308 - Permanent (Strict)', 'subdomain-redirect-counter' ); ?></option>
			</select>
		</div>
		<p class="description">
			<?php esc_html_e( 'Choose what happens when a subdomain has no mapping and no matching page/post slug.', 'subdomain-redirect-counter' ); ?>
		</p>
		<script>
		(function() {
			var radios = document.querySelectorAll('.src-unmapped-behavior-radio');
			var codeDiv = document.querySelector('.src-unmapped-redirect-code');
			radios.forEach(function(radio) {
				radio.addEventListener('change', function() {
					codeDiv.style.display = this.value === 'redirect' ? '' : 'none';
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Handle reset statistics action.
	 *
	 * @return void
	 */
	public function handle_reset_stats(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'subdomain-redirect-counter' ) );
		}

		check_admin_referer( 'src_reset_stats', 'src_nonce' );

		SRC_Statistics::reset_all();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'subdomain-redirect-counter-stats',
					'message' => 'stats_reset',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle clear logs action.
	 *
	 * @return void
	 */
	public function handle_clear_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'subdomain-redirect-counter' ) );
		}

		check_admin_referer( 'src_clear_logs', 'src_nonce' );

		SRC_Logger::clear_logs();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'subdomain-redirect-counter-logs',
					'message' => 'logs_cleared',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the mappings page.
	 *
	 * @return void
	 */
	public function render_mappings_page(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		switch ( $action ) {
			case 'add':
				$this->render_mapping_form();
				break;
			case 'edit':
				$this->render_mapping_form( $id );
				break;
			default:
				$this->render_mappings_list();
				break;
		}
	}

	/**
	 * Render the mappings list.
	 *
	 * @return void
	 */
	private function render_mappings_list(): void {
		$mappings = SRC_Mappings::get_all();
		$message  = isset( $_GET['message'] ) ? sanitize_key( $_GET['message'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap src-admin-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Subdomain Mappings', 'subdomain-redirect-counter' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=subdomain-redirect-counter-mappings&action=add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'subdomain-redirect-counter' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php if ( 'added' === $message ) : ?>
				<div class="src-notice src-notice-success">
					<p><?php esc_html_e( 'Mapping added successfully.', 'subdomain-redirect-counter' ); ?></p>
				</div>
			<?php elseif ( 'updated' === $message ) : ?>
				<div class="src-notice src-notice-success">
					<p><?php esc_html_e( 'Mapping updated successfully.', 'subdomain-redirect-counter' ); ?></p>
				</div>
			<?php elseif ( 'deleted' === $message ) : ?>
				<div class="src-notice src-notice-success">
					<p><?php esc_html_e( 'Mapping deleted successfully.', 'subdomain-redirect-counter' ); ?></p>
				</div>
			<?php elseif ( 'error' === $message ) : ?>
				<div class="src-notice src-notice-error">
					<p><?php esc_html_e( 'An error occurred. Please try again.', 'subdomain-redirect-counter' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="src-card">
				<p class="src-mapping-info">
					<?php esc_html_e( 'Configure which subdomain maps to a page, post, or external URL. Mappings take priority over automatic slug matching.', 'subdomain-redirect-counter' ); ?>
				</p>

				<?php if ( ! empty( $mappings ) ) : ?>
					<table class="src-table src-table-full">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Subdomain', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Target', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Status', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Created', 'subdomain-redirect-counter' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'subdomain-redirect-counter' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $mappings as $mapping ) : ?>
								<?php
								$mapping_type = $mapping->mapping_type ?? 'post';
								$domain       = wp_parse_url( home_url(), PHP_URL_HOST );
								?>
								<tr>
									<td>
										<code><?php echo esc_html( $mapping->subdomain ); ?>.<?php echo esc_html( $domain ); ?></code>
									</td>
									<td>
										<?php if ( 'url' === $mapping_type ) : ?>
											<code class="src-url-target"><?php echo esc_html( $mapping->redirect_url ); ?></code>
											<span class="src-post-type">(<?php echo esc_html( $mapping->redirect_code ?? 301 ); ?>)</span>
										<?php elseif ( 'home' === $mapping_type ) : ?>
											<code class="src-url-target"><?php echo esc_url( home_url( '/' ) ); ?></code>
											<span class="src-post-type">(<?php echo esc_html( $mapping->redirect_code ?? 302 ); ?> <?php esc_html_e( 'Homepage', 'subdomain-redirect-counter' ); ?>)</span>
										<?php else : ?>
											<?php
											$post       = get_post( $mapping->post_id );
											$post_title = $post ? $post->post_title : __( '(Deleted)', 'subdomain-redirect-counter' );
											$post_type  = $post ? get_post_type_object( $post->post_type ) : null;
											$type_label = $post_type ? $post_type->labels->singular_name : '';
											$edit_link  = $post ? get_edit_post_link( $post->ID ) : '';
											?>
											<?php if ( $post && $edit_link ) : ?>
												<a href="<?php echo esc_url( $edit_link ); ?>">
													<?php echo esc_html( $post_title ); ?>
												</a>
												<span class="src-post-type">(<?php echo esc_html( $type_label ); ?>)</span>
											<?php else : ?>
												<span class="src-deleted"><?php echo esc_html( $post_title ); ?></span>
											<?php endif; ?>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $mapping->is_active ) : ?>
											<span class="src-status src-status-active"><?php esc_html_e( 'Active', 'subdomain-redirect-counter' ); ?></span>
										<?php else : ?>
											<span class="src-status src-status-inactive"><?php esc_html_e( 'Inactive', 'subdomain-redirect-counter' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php
										echo esc_html(
											wp_date(
												get_option( 'date_format' ),
												strtotime( $mapping->created_at )
											)
										);
										?>
									</td>
									<td class="src-actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=subdomain-redirect-counter-mappings&action=edit&id=' . $mapping->id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'subdomain-redirect-counter' ); ?>
										</a>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="src-inline-form">
											<?php wp_nonce_field( 'src_delete_mapping_' . $mapping->id, 'src_nonce' ); ?>
											<input type="hidden" name="action" value="src_delete_mapping">
											<input type="hidden" name="mapping_id" value="<?php echo esc_attr( $mapping->id ); ?>">
											<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this mapping?', 'subdomain-redirect-counter' ); ?>');">
												<?php esc_html_e( 'Delete', 'subdomain-redirect-counter' ); ?>
											</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="src-no-data"><?php esc_html_e( 'No mappings configured yet. Click "Add New" to create your first mapping.', 'subdomain-redirect-counter' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the mapping add/edit form.
	 *
	 * @param int $id The mapping ID (0 for new).
	 * @return void
	 */
	private function render_mapping_form( int $id = 0 ): void {
		$mapping      = $id > 0 ? SRC_Mappings::get_by_id( $id ) : null;
		$is_edit      = null !== $mapping;
		$subdomain    = $mapping->subdomain ?? '';
		$mapping_type = $mapping->mapping_type ?? 'post';
		$post_id      = $mapping->post_id ?? 0;
		$redirect_url = $mapping->redirect_url ?? '';
		$is_active    = $mapping->is_active ?? 1;
		$domain       = wp_parse_url( home_url(), PHP_URL_HOST );

		// Get all public pages and posts for the dropdown.
		$posts = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap src-admin-wrap">
			<h1>
				<?php
				if ( $is_edit ) {
					esc_html_e( 'Edit Mapping', 'subdomain-redirect-counter' );
				} else {
					esc_html_e( 'Add New Mapping', 'subdomain-redirect-counter' );
				}
				?>
			</h1>

			<div class="src-card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php
					if ( $is_edit ) {
						wp_nonce_field( 'src_edit_mapping_' . $id, 'src_nonce' );
						echo '<input type="hidden" name="action" value="src_edit_mapping">';
						echo '<input type="hidden" name="mapping_id" value="' . esc_attr( $id ) . '">';
					} else {
						wp_nonce_field( 'src_add_mapping', 'src_nonce' );
						echo '<input type="hidden" name="action" value="src_add_mapping">';
					}
					?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="subdomain"><?php esc_html_e( 'Subdomain', 'subdomain-redirect-counter' ); ?></label>
							</th>
							<td>
								<div class="src-subdomain-input">
									<input type="text" name="subdomain" id="subdomain" value="<?php echo esc_attr( $subdomain ); ?>" class="regular-text" required pattern="[a-z0-9]([a-z0-9-]*[a-z0-9])?" title="<?php esc_attr_e( 'Lowercase letters, numbers, and hyphens only', 'subdomain-redirect-counter' ); ?>">
									<span class="src-domain-suffix">.<?php echo esc_html( $domain ); ?></span>
								</div>
								<p class="description">
									<?php esc_html_e( 'Enter the subdomain (e.g., "tickets" for tickets.yourdomain.com).', 'subdomain-redirect-counter' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Redirect Type', 'subdomain-redirect-counter' ); ?>
							</th>
							<td>
								<fieldset>
									<label>
										<input type="radio" name="mapping_type" value="post" <?php checked( $mapping_type, 'post' ); ?> class="src-mapping-type-radio">
										<?php esc_html_e( 'Page/Post', 'subdomain-redirect-counter' ); ?>
									</label>
									<br>
									<label>
										<input type="radio" name="mapping_type" value="url" <?php checked( $mapping_type, 'url' ); ?> class="src-mapping-type-radio">
										<?php esc_html_e( 'External URL', 'subdomain-redirect-counter' ); ?>
									</label>
									<br>
									<label>
										<input type="radio" name="mapping_type" value="home" <?php checked( $mapping_type, 'home' ); ?> class="src-mapping-type-radio">
										<?php esc_html_e( 'Homepage Redirect', 'subdomain-redirect-counter' ); ?>
									</label>
								</fieldset>
								<p class="description">
									<?php esc_html_e( 'Choose whether to display a page/post, redirect to an external URL, or redirect to the homepage.', 'subdomain-redirect-counter' ); ?>
								</p>
							</td>
						</tr>
						<tr class="src-target-post-row" <?php echo 'url' === $mapping_type ? 'style="display:none;"' : ''; ?>>
							<th scope="row">
								<label for="post_id"><?php esc_html_e( 'Target Page/Post', 'subdomain-redirect-counter' ); ?></label>
							</th>
							<td>
								<select name="post_id" id="post_id" class="regular-text">
									<option value=""><?php esc_html_e( '— Select —', 'subdomain-redirect-counter' ); ?></option>
									<?php
									$pages = array_filter( $posts, fn( $p ) => 'page' === $p->post_type );
									$other = array_filter( $posts, fn( $p ) => 'page' !== $p->post_type );
									?>
									<?php if ( ! empty( $pages ) ) : ?>
										<optgroup label="<?php esc_attr_e( 'Pages', 'subdomain-redirect-counter' ); ?>">
											<?php foreach ( $pages as $p ) : ?>
												<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $post_id, $p->ID ); ?>>
													<?php echo esc_html( $p->post_title ); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
									<?php endif; ?>
									<?php if ( ! empty( $other ) ) : ?>
										<optgroup label="<?php esc_attr_e( 'Posts', 'subdomain-redirect-counter' ); ?>">
											<?php foreach ( $other as $p ) : ?>
												<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $post_id, $p->ID ); ?>>
													<?php echo esc_html( $p->post_title ); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
									<?php endif; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select the page or post to display when this subdomain is accessed.', 'subdomain-redirect-counter' ); ?>
								</p>
							</td>
						</tr>
						<tr class="src-target-url-row" <?php echo 'post' === $mapping_type ? 'style="display:none;"' : ''; ?>>
							<th scope="row">
								<label for="redirect_url"><?php esc_html_e( 'Redirect URL', 'subdomain-redirect-counter' ); ?></label>
							</th>
							<td>
								<input type="url" name="redirect_url" id="redirect_url" value="<?php echo esc_url( $redirect_url ); ?>" class="regular-text code" placeholder="https://example.com/page">
								<p class="description">
									<?php esc_html_e( 'Enter the full URL to redirect to (including https://).', 'subdomain-redirect-counter' ); ?>
								</p>
							</td>
						</tr>
						<tr class="src-target-url-row" <?php echo 'url' !== $mapping_type ? 'style="display:none;"' : ''; ?>>
							<th scope="row">
								<label for="redirect_code"><?php esc_html_e( 'HTTP Status Code', 'subdomain-redirect-counter' ); ?></label>
							</th>
							<td>
								<?php $redirect_code = $mapping->redirect_code ?? 301; ?>
								<select name="redirect_code" id="redirect_code">
									<option value="301" <?php selected( $redirect_code, 301 ); ?>><?php esc_html_e( '301 - Permanent Redirect', 'subdomain-redirect-counter' ); ?></option>
									<option value="302" <?php selected( $redirect_code, 302 ); ?>><?php esc_html_e( '302 - Temporary Redirect (Found)', 'subdomain-redirect-counter' ); ?></option>
									<option value="307" <?php selected( $redirect_code, 307 ); ?>><?php esc_html_e( '307 - Temporary Redirect (Strict)', 'subdomain-redirect-counter' ); ?></option>
									<option value="308" <?php selected( $redirect_code, 308 ); ?>><?php esc_html_e( '308 - Permanent Redirect (Strict)', 'subdomain-redirect-counter' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( '301/308 are cached by browsers. 302/307 are not cached. Strict variants preserve the HTTP method.', 'subdomain-redirect-counter' ); ?>
								</p>
							</td>
						</tr>
						<tr class="src-target-home-row" <?php echo 'home' !== $mapping_type ? 'style="display:none;"' : ''; ?>>
							<th scope="row">
								<label for="home_redirect_code"><?php esc_html_e( 'HTTP Status Code', 'subdomain-redirect-counter' ); ?></label>
							</th>
							<td>
								<?php $home_redirect_code = 'home' === $mapping_type ? ( $mapping->redirect_code ?? 302 ) : 302; ?>
								<select name="home_redirect_code" id="home_redirect_code">
									<option value="301" <?php selected( $home_redirect_code, 301 ); ?>><?php esc_html_e( '301 - Permanent Redirect', 'subdomain-redirect-counter' ); ?></option>
									<option value="302" <?php selected( $home_redirect_code, 302 ); ?>><?php esc_html_e( '302 - Temporary Redirect (Recommended)', 'subdomain-redirect-counter' ); ?></option>
									<option value="307" <?php selected( $home_redirect_code, 307 ); ?>><?php esc_html_e( '307 - Temporary Redirect (Strict)', 'subdomain-redirect-counter' ); ?></option>
									<option value="308" <?php selected( $home_redirect_code, 308 ); ?>><?php esc_html_e( '308 - Permanent Redirect (Strict)', 'subdomain-redirect-counter' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Redirect to:', 'subdomain-redirect-counter' ); ?> <code><?php echo esc_url( home_url( '/' ) ); ?></code>
								</p>
							</td>
						</tr>
						<?php if ( $is_edit ) : ?>
							<tr>
								<th scope="row">
									<label for="is_active"><?php esc_html_e( 'Status', 'subdomain-redirect-counter' ); ?></label>
								</th>
								<td>
									<label>
										<input type="checkbox" name="is_active" id="is_active" value="1" <?php checked( $is_active ); ?>>
										<?php esc_html_e( 'Active', 'subdomain-redirect-counter' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Uncheck to disable this mapping without deleting it.', 'subdomain-redirect-counter' ); ?>
									</p>
								</td>
							</tr>
						<?php endif; ?>
					</table>

					<p class="submit">
						<?php submit_button( $is_edit ? __( 'Update Mapping', 'subdomain-redirect-counter' ) : __( 'Add Mapping', 'subdomain-redirect-counter' ), 'primary', 'submit', false ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=subdomain-redirect-counter-mappings' ) ); ?>" class="button">
							<?php esc_html_e( 'Cancel', 'subdomain-redirect-counter' ); ?>
						</a>
					</p>
				</form>
			</div>
		</div>
		<script>
		(function() {
			var radios = document.querySelectorAll('.src-mapping-type-radio');
			var postRow = document.querySelector('.src-target-post-row');
			var urlRows = document.querySelectorAll('.src-target-url-row');
			var homeRows = document.querySelectorAll('.src-target-home-row');

			function toggleFields() {
				var selected = document.querySelector('.src-mapping-type-radio:checked');
				var type = selected ? selected.value : 'post';

				// Hide all by default
				postRow.style.display = 'none';
				urlRows.forEach(function(row) { row.style.display = 'none'; });
				homeRows.forEach(function(row) { row.style.display = 'none'; });

				// Show appropriate fields
				if (type === 'url') {
					urlRows.forEach(function(row) { row.style.display = ''; });
				} else if (type === 'home') {
					homeRows.forEach(function(row) { row.style.display = ''; });
				} else {
					postRow.style.display = '';
				}
			}

			radios.forEach(function(radio) {
				radio.addEventListener('change', toggleFields);
			});
		})();
		</script>
		<?php
	}

	/**
	 * Handle add mapping action.
	 *
	 * @return void
	 */
	public function handle_add_mapping(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'subdomain-redirect-counter' ) );
		}

		check_admin_referer( 'src_add_mapping', 'src_nonce' );

		$subdomain          = isset( $_POST['subdomain'] ) ? sanitize_key( $_POST['subdomain'] ) : '';
		$mapping_type       = isset( $_POST['mapping_type'] ) ? sanitize_key( $_POST['mapping_type'] ) : 'post';
		$post_id            = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw provides sanitization.
		$redirect_url       = isset( $_POST['redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) : '';
		$redirect_code      = isset( $_POST['redirect_code'] ) ? absint( $_POST['redirect_code'] ) : 301;
		$home_redirect_code = isset( $_POST['home_redirect_code'] ) ? absint( $_POST['home_redirect_code'] ) : 302;

		// Use home_redirect_code for home type.
		if ( 'home' === $mapping_type ) {
			$redirect_code = $home_redirect_code;
		}

		// Validate based on type.
		$is_valid = false;
		if ( 'url' === $mapping_type ) {
			$is_valid = ! empty( $subdomain ) && ! empty( $redirect_url );
		} elseif ( 'home' === $mapping_type ) {
			$is_valid = ! empty( $subdomain );
		} else {
			$is_valid = ! empty( $subdomain ) && $post_id > 0;
		}

		if ( ! $is_valid ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'subdomain-redirect-counter-mappings',
						'message' => 'error',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( SRC_Mappings::subdomain_exists( $subdomain ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'subdomain-redirect-counter-mappings',
						'action'  => 'add',
						'message' => 'exists',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$result = SRC_Mappings::add( $subdomain, $post_id, $mapping_type, $redirect_url, $redirect_code );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'subdomain-redirect-counter-mappings',
					'message' => $result ? 'added' : 'error',
				),
				admin_url( 'admin.php' )
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'subdomain-redirect-counter' ) );
		}

		$id = isset( $_POST['mapping_id'] ) ? absint( $_POST['mapping_id'] ) : 0;

		check_admin_referer( 'src_edit_mapping_' . $id, 'src_nonce' );

		$subdomain          = isset( $_POST['subdomain'] ) ? sanitize_key( $_POST['subdomain'] ) : '';
		$mapping_type       = isset( $_POST['mapping_type'] ) ? sanitize_key( $_POST['mapping_type'] ) : 'post';
		$post_id            = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw provides sanitization.
		$redirect_url       = isset( $_POST['redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) : '';
		$redirect_code      = isset( $_POST['redirect_code'] ) ? absint( $_POST['redirect_code'] ) : 301;
		$home_redirect_code = isset( $_POST['home_redirect_code'] ) ? absint( $_POST['home_redirect_code'] ) : 302;
		$is_active          = isset( $_POST['is_active'] );

		// Use home_redirect_code for home type.
		if ( 'home' === $mapping_type ) {
			$redirect_code = $home_redirect_code;
		}

		// Validate based on type.
		$is_valid = false;
		if ( 'url' === $mapping_type ) {
			$is_valid = ! empty( $subdomain ) && ! empty( $redirect_url ) && $id > 0;
		} elseif ( 'home' === $mapping_type ) {
			$is_valid = ! empty( $subdomain ) && $id > 0;
		} else {
			$is_valid = ! empty( $subdomain ) && $post_id > 0 && $id > 0;
		}

		if ( ! $is_valid ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'subdomain-redirect-counter-mappings',
						'message' => 'error',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( SRC_Mappings::subdomain_exists( $subdomain, $id ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'subdomain-redirect-counter-mappings',
						'action'  => 'edit',
						'id'      => $id,
						'message' => 'exists',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$result = SRC_Mappings::update( $id, $subdomain, $post_id, $mapping_type, $redirect_url, $redirect_code );

		if ( $result ) {
			SRC_Mappings::set_active( $id, $is_active );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'subdomain-redirect-counter-mappings',
					'message' => $result ? 'updated' : 'error',
				),
				admin_url( 'admin.php' )
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'subdomain-redirect-counter' ) );
		}

		$id = isset( $_POST['mapping_id'] ) ? absint( $_POST['mapping_id'] ) : 0;

		check_admin_referer( 'src_delete_mapping_' . $id, 'src_nonce' );

		$result = SRC_Mappings::delete( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'subdomain-redirect-counter-mappings',
					'message' => $result ? 'deleted' : 'error',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}

<?php
/**
 * Plugin Name: Subdomain Redirect Counter
 * Plugin URI: https://example.com/subdomain-redirect-counter
 * Description: Intercepts subdomain or alias domain requests and redirects them to local permalinks while tracking statistics.
 * Version: 1.5.0
 * Network: true
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: John Gaudet
 * Author URI: https://cardandcraft.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: subdomain-redirect-counter
 * Domain Path: /languages
 *
 * @package Subdomain_Redirect_Counter
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SRC_VERSION', '1.5.0' );
define( 'SRC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SRC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SRC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class Subdomain_Redirect_Counter {

	/**
	 * Single instance of the class.
	 *
	 * @var Subdomain_Redirect_Counter|null
	 */
	private static ?Subdomain_Redirect_Counter $instance = null;

	/**
	 * Get the single instance.
	 *
	 * @return Subdomain_Redirect_Counter
	 */
	public static function get_instance(): Subdomain_Redirect_Counter {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		require_once SRC_PLUGIN_DIR . 'includes/class-src-database.php';
		require_once SRC_PLUGIN_DIR . 'includes/class-src-mappings.php';
		require_once SRC_PLUGIN_DIR . 'includes/class-src-statistics.php';
		require_once SRC_PLUGIN_DIR . 'includes/class-src-logger.php';
		require_once SRC_PLUGIN_DIR . 'includes/class-src-interceptor.php';

		if ( is_admin() ) {
			require_once SRC_PLUGIN_DIR . 'admin/class-src-admin.php';
		}

		// Network admin for multisite.
		if ( is_multisite() && is_network_admin() ) {
			require_once SRC_PLUGIN_DIR . 'admin/class-src-network-admin.php';
		}
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Multisite hooks for new site creation and deletion.
		add_action( 'wp_initialize_site', array( $this, 'on_new_site_created' ), 10, 2 );
		add_action( 'wp_uninitialize_site', array( $this, 'on_site_deleted' ), 10, 1 );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'src_log_rotation_cron', array( $this, 'run_log_rotation' ) );

		if ( is_admin() ) {
			SRC_Admin::get_instance();
		}

		// Network admin for multisite.
		if ( is_multisite() && is_network_admin() ) {
			SRC_Network_Admin::get_instance();
		}
	}

	/**
	 * Plugin activation.
	 *
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 * @return void
	 */
	public function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$this->network_activate();
		} else {
			$this->single_site_activate();
		}
	}

	/**
	 * Activate the plugin for all sites in the network.
	 *
	 * @return void
	 */
	private function network_activate(): void {
		$sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );
			$this->single_site_activate();
			restore_current_blog();
		}
	}

	/**
	 * Activate the plugin for a single site.
	 *
	 * @return void
	 */
	private function single_site_activate(): void {
		SRC_Database::create_tables();

		// Set default options.
		$defaults = array(
			'enabled'             => true,
			'logging_enabled'     => false,
			'excluded_subdomains' => array( 'www', 'mail', 'ftp', 'cpanel', 'webmail' ),
			'log_retention_days'  => 0,
		);

		if ( false === get_option( 'src_settings' ) ) {
			add_option( 'src_settings', $defaults );
		}

		// Schedule log rotation cron if not already scheduled.
		if ( ! wp_next_scheduled( 'src_log_rotation_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'src_log_rotation_cron' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 *
	 * @param bool $network_wide Whether the plugin is being deactivated network-wide.
	 * @return void
	 */
	public function deactivate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$this->network_deactivate();
		} else {
			$this->single_site_deactivate();
		}
	}

	/**
	 * Deactivate the plugin for all sites in the network.
	 *
	 * @return void
	 */
	private function network_deactivate(): void {
		$sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );
			$this->single_site_deactivate();
			restore_current_blog();
		}
	}

	/**
	 * Deactivate the plugin for a single site.
	 *
	 * @return void
	 */
	private function single_site_deactivate(): void {
		// Unschedule log rotation cron.
		$timestamp = wp_next_scheduled( 'src_log_rotation_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'src_log_rotation_cron' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'subdomain-redirect-counter',
			false,
			dirname( SRC_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize the interceptor.
	 *
	 * @return void
	 */
	public function init(): void {
		$settings = get_option( 'src_settings', array() );

		if ( ! empty( $settings['enabled'] ) ) {
			SRC_Interceptor::get_instance();
		}
	}

	/**
	 * Run log rotation to delete old entries.
	 *
	 * @return void
	 */
	public function run_log_rotation(): void {
		$settings       = get_option( 'src_settings', array() );
		$retention_days = $settings['log_retention_days'] ?? 0;

		// Only run if retention is enabled (non-zero).
		if ( $retention_days > 0 ) {
			SRC_Logger::delete_old_logs( $retention_days );
		}
	}

	/**
	 * Handle new site creation in multisite.
	 *
	 * Sets up the plugin tables and options for the new site if the plugin
	 * is network activated.
	 *
	 * @param WP_Site $new_site New site object.
	 * @param array   $args     Arguments for the initialization (unused).
	 * @return void
	 */
	public function on_new_site_created( WP_Site $new_site, array $args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Only set up if plugin is network activated.
		if ( ! is_plugin_active_for_network( SRC_PLUGIN_BASENAME ) ) {
			return;
		}

		switch_to_blog( $new_site->blog_id );
		$this->single_site_activate();
		restore_current_blog();
	}

	/**
	 * Handle site deletion in multisite.
	 *
	 * Cleans up the plugin tables and options for the deleted site.
	 *
	 * @param WP_Site $old_site Site being deleted.
	 * @return void
	 */
	public function on_site_deleted( WP_Site $old_site ): void {
		switch_to_blog( $old_site->blog_id );

		// Clean up cron.
		$timestamp = wp_next_scheduled( 'src_log_rotation_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'src_log_rotation_cron' );
		}

		// Drop tables for this site.
		SRC_Database::drop_tables();

		// Delete options.
		delete_option( 'src_settings' );
		delete_option( 'src_db_version' );

		restore_current_blog();
	}
}

/**
 * Initialize the plugin.
 *
 * @return Subdomain_Redirect_Counter
 */
function subdomain_redirect_counter(): Subdomain_Redirect_Counter {
	return Subdomain_Redirect_Counter::get_instance();
}

// Start the plugin.
subdomain_redirect_counter();

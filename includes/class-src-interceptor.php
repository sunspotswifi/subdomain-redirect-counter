<?php
/**
 * Subdomain interceptor class.
 *
 * @package Subdomain_Redirect_Counter
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles subdomain interception and redirection.
 *
 * @since 1.0.0
 */
class SRC_Interceptor {

	/**
	 * Single instance of the class.
	 *
	 * @var SRC_Interceptor|null
	 */
	private static ?SRC_Interceptor $instance = null;

	/**
	 * The detected subdomain.
	 *
	 * @var string|null
	 */
	private ?string $subdomain = null;

	/**
	 * Whether current request is for an unmapped subdomain.
	 *
	 * @var bool
	 */
	private bool $is_unmapped = false;

	/**
	 * Get the single instance.
	 *
	 * @return SRC_Interceptor
	 */
	public static function get_instance(): SRC_Interceptor {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Check for root domain redirect first (highest priority).
		$this->maybe_do_domain_redirect();

		$this->subdomain = $this->detect_subdomain();

		if ( $this->subdomain ) {
			// Check for URL redirect mapping as early as possible.
			// Use 'wp' action which fires after query parsing but before template selection.
			add_action( 'wp', array( $this, 'maybe_do_url_redirect' ), 0 );
			add_action( 'template_redirect', array( $this, 'handle_redirect' ), 1 );
			add_filter( 'request', array( $this, 'modify_request' ), 1 );

			// Add canonical URL for SEO when showing content inline.
			add_action( 'wp_head', array( $this, 'output_canonical_url' ), 1 );
			add_filter( 'wpseo_canonical', array( $this, 'filter_yoast_canonical' ) );
			add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_yoast_canonical' ) );
		}
	}

	/**
	 * Check for and perform root domain redirect.
	 *
	 * This handles redirecting entire domains (e.g., cardandcraft.org → cardandcraft.com)
	 * with statistics counting and optional logging.
	 *
	 * @return void
	 */
	private function maybe_do_domain_redirect(): void {
		// Get current host.
		$host = isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
			: '';

		if ( empty( $host ) ) {
			return;
		}

		// Remove port if present.
		$host = strtok( $host, ':' );

		// Normalize: remove www prefix for matching.
		$host_normalized = preg_replace( '/^www\./i', '', $host );

		// Get redirect domains from settings.
		$settings         = get_option( 'src_settings', array() );
		$redirect_domains = $settings['redirect_domains'] ?? array();

		if ( empty( $redirect_domains ) || ! is_array( $redirect_domains ) ) {
			return;
		}

		// Check if current host matches any redirect domain.
		foreach ( $redirect_domains as $redirect ) {
			if ( ! is_array( $redirect ) || empty( $redirect['domain'] ) || empty( $redirect['target_url'] ) ) {
				continue;
			}

			$redirect_domain = preg_replace( '/^www\./i', '', strtolower( trim( $redirect['domain'] ) ) );

			// Match with or without www.
			if ( $host_normalized === $redirect_domain ) {
				$target_url    = $redirect['target_url'];
				$redirect_code = isset( $redirect['redirect_code'] ) ? absint( $redirect['redirect_code'] ) : 301;

				// Validate redirect code.
				if ( ! in_array( $redirect_code, array( 301, 302, 307, 308 ), true ) ) {
					$redirect_code = 301;
				}

				// Build source URL for logging.
				$scheme     = is_ssl() ? 'https' : 'http';
				$request    = isset( $_SERVER['REQUEST_URI'] )
					? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
					: '/';
				$source_url = $scheme . '://' . $host . $request;

				// Append the request path to target URL if not just redirecting to root.
				if ( '/' !== $request && ! empty( $request ) ) {
					$target_url = rtrim( $target_url, '/' ) . $request;
				}

				// Record statistics using the domain as the "subdomain" identifier.
				$stat_key    = '@' . $redirect_domain; // Prefix with @ to distinguish from subdomains.
				$target_path = '→ ' . $redirect['target_url'];
				SRC_Statistics::record_redirect( $stat_key, $target_path );

				// Log the redirect if enabled.
				SRC_Logger::log( $stat_key, $target_path, $source_url );

				/**
				 * Fires before a domain redirect is performed.
				 *
				 * @since 1.5.0
				 *
				 * @param string $redirect_domain The domain being redirected from.
				 * @param string $target_url      The target URL.
				 * @param string $source_url      The original source URL.
				 * @param int    $redirect_code   The HTTP redirect code.
				 */
				do_action( 'src_domain_redirect', $redirect_domain, $target_url, $source_url, $redirect_code );

				// Perform the redirect.
				// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Intentional external URL redirect.
				wp_redirect( $target_url, $redirect_code );
				exit;
			}
		}
	}

	/**
	 * Detect subdomain from the current request.
	 *
	 * @return string|null The detected subdomain or null.
	 */
	private function detect_subdomain(): ?string {
		// Get current host.
		$host = isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
			: '';

		if ( empty( $host ) ) {
			return null;
		}

		// Remove port if present.
		$host = strtok( $host, ':' );

		// Get domains to check (main domain + aliased domains).
		$domains_to_check = $this->get_valid_domains();

		foreach ( $domains_to_check as $domain ) {
			// Check if current host is the base domain (no subdomain).
			if ( $domain === $host || 'www.' . $domain === $host ) {
				continue;
			}

			// Extract subdomain for this domain.
			$pattern = '/^([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)\.' . preg_quote( $domain, '/' ) . '$/i';

			if ( preg_match( $pattern, $host, $matches ) ) {
				$subdomain = strtolower( $matches[1] );

				// Check if subdomain is excluded.
				if ( $this->is_excluded( $subdomain ) ) {
					return null;
				}

				return $subdomain;
			}
		}

		return null;
	}

	/**
	 * Get all valid domains for subdomain detection.
	 *
	 * Includes the main site domain plus any configured aliased domains.
	 *
	 * @return array List of valid domains.
	 */
	private function get_valid_domains(): array {
		$domains = array();

		// Get the site's main domain.
		$site_url    = get_option( 'siteurl' );
		$parsed_url  = wp_parse_url( $site_url );
		$main_domain = $parsed_url['host'] ?? '';

		// Remove www if present from main domain.
		$main_domain = preg_replace( '/^www\./i', '', $main_domain );

		if ( ! empty( $main_domain ) ) {
			$domains[] = $main_domain;
		}

		// Get aliased domains from settings.
		$settings        = get_option( 'src_settings', array() );
		$aliased_domains = $settings['aliased_domains'] ?? array();

		if ( ! empty( $aliased_domains ) && is_array( $aliased_domains ) ) {
			foreach ( $aliased_domains as $aliased_domain ) {
				// Remove www if present.
				$aliased_domain = preg_replace( '/^www\./i', '', $aliased_domain );
				if ( ! empty( $aliased_domain ) && ! in_array( $aliased_domain, $domains, true ) ) {
					$domains[] = $aliased_domain;
				}
			}
		}

		return $domains;
	}

	/**
	 * Check if subdomain is in the exclusion list.
	 *
	 * @param string $subdomain The subdomain to check.
	 * @return bool Whether the subdomain is excluded.
	 */
	private function is_excluded( string $subdomain ): bool {
		$settings = get_option( 'src_settings', array() );
		$excluded = $settings['excluded_subdomains'] ?? array( 'www', 'mail', 'ftp', 'cpanel', 'webmail' );

		return in_array( $subdomain, $excluded, true );
	}

	/**
	 * Check for URL or home redirect mapping and perform redirect if applicable.
	 *
	 * @return void
	 */
	public function maybe_do_url_redirect(): void {
		if ( ! $this->subdomain ) {
			return;
		}

		// Check if this subdomain has a URL redirect mapping.
		$redirect_url = SRC_Mappings::get_redirect_url( $this->subdomain );

		// Check if this is a home redirect mapping.
		$is_home_redirect = SRC_Mappings::is_home_redirect( $this->subdomain );

		if ( ! $redirect_url && ! $is_home_redirect ) {
			return;
		}

		// Get the configured redirect code.
		$redirect_code = SRC_Mappings::get_redirect_code( $this->subdomain );

		// For home redirect, use the homepage URL.
		if ( $is_home_redirect ) {
			$redirect_url = home_url( '/' );
		}

		// Get the original URL for logging.
		$scheme     = is_ssl() ? 'https' : 'http';
		$host       = isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
			: '';
		$request    = isset( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		$source_url = $scheme . '://' . $host . $request;

		// Record statistics.
		$target_path = $is_home_redirect ? '/ (home redirect)' : $redirect_url;
		SRC_Statistics::record_redirect( $this->subdomain, $target_path );

		// Log the redirect if enabled.
		SRC_Logger::log( $this->subdomain, $target_path, $source_url );

		/**
		 * Fires before a URL redirect is performed.
		 *
		 * @since 1.2.0
		 *
		 * @param string $subdomain     The subdomain that was detected.
		 * @param string $redirect_url  The target URL.
		 * @param string $source_url    The original source URL.
		 * @param int    $redirect_code The HTTP redirect code.
		 */
		do_action( 'src_url_redirect', $this->subdomain, $redirect_url, $source_url, $redirect_code );

		// Perform the redirect with the configured status code.
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Intentional external URL redirect.
		wp_redirect( $redirect_url, $redirect_code );
		exit;
	}

	/**
	 * Modify the WordPress request to use the subdomain as the path.
	 *
	 * @param array $query_vars The query variables.
	 * @return array Modified query variables.
	 */
	public function modify_request( array $query_vars ): array {
		if ( ! $this->subdomain ) {
			return $query_vars;
		}

		// Skip if this is a URL or home redirect mapping (handled separately).
		if ( SRC_Mappings::get_redirect_url( $this->subdomain ) || SRC_Mappings::is_home_redirect( $this->subdomain ) ) {
			return $query_vars;
		}

		// First, check for a configured mapping.
		$mapped_post = SRC_Mappings::get_mapped_post( $this->subdomain );

		if ( $mapped_post ) {
			if ( 'page' === $mapped_post->post_type ) {
				$query_vars = array(
					'page_id' => $mapped_post->ID,
				);
			} else {
				$query_vars = array(
					'p'         => $mapped_post->ID,
					'post_type' => $mapped_post->post_type,
				);
			}
			return $query_vars;
		}

		// Fall back to slug-based lookup.
		// Check if a page exists with the subdomain slug.
		$page = get_page_by_path( $this->subdomain );

		if ( $page ) {
			$query_vars = array(
				'page_id' => $page->ID,
			);
		} else {
			// Check for a post or custom post type.
			$post = $this->find_post_by_slug( $this->subdomain );

			if ( $post ) {
				$query_vars = array(
					'p'         => $post->ID,
					'post_type' => $post->post_type,
				);
			} else {
				// No mapping found - redirect to homepage.
				// Mark this as an unmapped subdomain for tracking.
				$this->is_unmapped = true;

				// Show the homepage.
				$front_page_id = (int) get_option( 'page_on_front' );
				if ( $front_page_id > 0 ) {
					$query_vars = array(
						'page_id' => $front_page_id,
					);
				} else {
					// Blog-style homepage.
					$query_vars = array();
				}
			}
		}

		return $query_vars;
	}

	/**
	 * Find a post by slug across all post types.
	 *
	 * @param string $slug The post slug.
	 * @return WP_Post|null The found post or null.
	 */
	private function find_post_by_slug( string $slug ): ?WP_Post {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		$args = array(
			'name'           => $slug,
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		);

		$posts = get_posts( $args );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Handle the redirect and record statistics.
	 *
	 * @return void
	 */
	public function handle_redirect(): void {
		if ( ! $this->subdomain ) {
			return;
		}

		// Skip if this is a URL or home redirect mapping (handled by maybe_do_url_redirect).
		if ( SRC_Mappings::get_redirect_url( $this->subdomain ) || SRC_Mappings::is_home_redirect( $this->subdomain ) ) {
			return;
		}

		// Get the original URL.
		$scheme     = is_ssl() ? 'https' : 'http';
		$host       = isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
			: '';
		$request    = isset( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		$source_url = $scheme . '://' . $host . $request;

		// Check if this is an unmapped subdomain that should redirect.
		if ( $this->is_unmapped ) {
			$settings         = get_option( 'src_settings', array() );
			$unmapped_behavior = $settings['unmapped_behavior'] ?? 'show';

			if ( 'redirect' === $unmapped_behavior ) {
				// Perform actual redirect to homepage.
				$redirect_code = $settings['unmapped_redirect_code'] ?? 302;
				$redirect_url  = home_url( '/' );
				$target_path   = '/ (unmapped redirect)';

				// Record statistics.
				SRC_Statistics::record_redirect( $this->subdomain, $target_path );

				// Log the redirect if enabled.
				SRC_Logger::log( $this->subdomain, $target_path, $source_url );

				/**
				 * Fires before an unmapped subdomain redirect is performed.
				 *
				 * @since 1.3.0
				 *
				 * @param string $subdomain     The subdomain that was detected.
				 * @param string $redirect_url  The homepage URL.
				 * @param string $source_url    The original source URL.
				 * @param int    $redirect_code The HTTP redirect code.
				 */
				do_action( 'src_unmapped_redirect', $this->subdomain, $redirect_url, $source_url, $redirect_code );

				// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting unmapped subdomain to homepage.
				wp_redirect( $redirect_url, $redirect_code );
				exit;
			}

			// Show homepage content (default behavior).
			$target_path = '/ (unmapped)';
		} else {
			// Determine target path.
			$target_path = '/' . $this->subdomain;

			// Check for mapped post.
			$mapped_post = SRC_Mappings::get_mapped_post( $this->subdomain );

			if ( $mapped_post ) {
				$permalink   = get_permalink( $mapped_post );
				$parsed_path = wp_parse_url( $permalink, PHP_URL_PATH );
				$target_path = $parsed_path ? $parsed_path : '/' . $mapped_post->post_name;
			}
		}

		// Record statistics.
		SRC_Statistics::record_redirect( $this->subdomain, $target_path );

		// Log the redirect if enabled.
		SRC_Logger::log( $this->subdomain, $target_path, $source_url );

		/**
		 * Fires after a subdomain redirect is processed.
		 *
		 * @since 1.0.0
		 *
		 * @param string $subdomain   The subdomain that was detected.
		 * @param string $target_path The target path.
		 * @param string $source_url  The original source URL.
		 */
		do_action( 'src_redirect_processed', $this->subdomain, $target_path, $source_url );
	}

	/**
	 * Get the current subdomain.
	 *
	 * @return string|null The current subdomain or null.
	 */
	public function get_subdomain(): ?string {
		return $this->subdomain;
	}

	/**
	 * Build the target URL from a subdomain.
	 *
	 * @param string $subdomain The subdomain.
	 * @return string The target URL.
	 */
	public static function build_target_url( string $subdomain ): string {
		return home_url( '/' . $subdomain . '/' );
	}

	/**
	 * Output canonical URL in wp_head for SEO.
	 *
	 * When content is displayed via a subdomain, this tells search engines
	 * that the canonical (preferred) URL is the main domain version.
	 *
	 * @return void
	 */
	public function output_canonical_url(): void {
		if ( ! $this->subdomain ) {
			return;
		}

		// Skip for redirect mappings (they redirect, don't show content).
		if ( SRC_Mappings::get_redirect_url( $this->subdomain ) || SRC_Mappings::is_home_redirect( $this->subdomain ) ) {
			return;
		}

		$canonical_url = $this->get_canonical_url();

		if ( $canonical_url ) {
			// Remove WordPress default canonical if present.
			remove_action( 'wp_head', 'rel_canonical' );

			echo '<link rel="canonical" href="' . esc_url( $canonical_url ) . '" />' . "\n";
		}
	}

	/**
	 * Filter Yoast SEO and Rank Math canonical URL.
	 *
	 * @param string $canonical The current canonical URL.
	 * @return string The filtered canonical URL.
	 */
	public function filter_yoast_canonical( string $canonical ): string {
		if ( ! $this->subdomain ) {
			return $canonical;
		}

		// Skip for redirect mappings.
		if ( SRC_Mappings::get_redirect_url( $this->subdomain ) || SRC_Mappings::is_home_redirect( $this->subdomain ) ) {
			return $canonical;
		}

		$our_canonical = $this->get_canonical_url();

		return $our_canonical ? $our_canonical : $canonical;
	}

	/**
	 * Get the canonical URL for the current subdomain request.
	 *
	 * @return string|null The canonical URL or null.
	 */
	private function get_canonical_url(): ?string {
		if ( ! $this->subdomain ) {
			return null;
		}

		// Check for mapped post first.
		$mapped_post = SRC_Mappings::get_mapped_post( $this->subdomain );

		if ( $mapped_post ) {
			return get_permalink( $mapped_post );
		}

		// Check for page by slug.
		$page = get_page_by_path( $this->subdomain );

		if ( $page ) {
			return get_permalink( $page );
		}

		// Check for post by slug.
		$post = $this->find_post_by_slug( $this->subdomain );

		if ( $post ) {
			return get_permalink( $post );
		}

		// Unmapped subdomain showing homepage.
		if ( $this->is_unmapped ) {
			return home_url( '/' );
		}

		return null;
	}
}

=== Subdomain Redirect Counter ===
Contributors: yourname
Tags: subdomain, redirect, counter, statistics, SEO
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Intercepts subdomain requests and redirects them to local permalinks while tracking statistics.

== Description ==

Subdomain Redirect Counter allows you to map subdomains to specific posts, pages, or custom URLs. When a visitor accesses a subdomain (e.g., `tickets.example.com`), they are seamlessly redirected to the corresponding content on your main domain (e.g., `example.com/tickets`).

= Features =

* Map subdomains to posts, pages, or custom URLs
* Track redirect statistics (count and last redirect time)
* Optional detailed logging with IP anonymization for privacy
* Configurable redirect codes (301, 302, 307, 308)
* Support for external URL redirects
* Multisite compatible with per-site configuration
* Exclude specific subdomains from processing (www, mail, ftp, etc.)
* Admin dashboard for managing mappings and viewing statistics

= Use Cases =

* Create memorable short URLs using subdomains
* Track marketing campaign performance
* Redirect legacy subdomains to new content
* Simplify access to specific sections of your site

== Installation ==

1. Upload the `subdomain-redirect-counter` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your subdomain mappings under 'Subdomain Redirects' in the admin menu

= DNS Configuration =

For the plugin to work, you need to configure a wildcard DNS record:

1. Add an A record for `*.yourdomain.com` pointing to your server IP
2. Or add a CNAME record for `*.yourdomain.com` pointing to `yourdomain.com`

== Frequently Asked Questions ==

= Does this work with multisite? =

Yes! Version 1.4.0 adds full multisite support. Each site in your network has its own mappings, statistics, and settings.

= What redirect codes are supported? =

The plugin supports HTTP redirect codes 301 (permanent), 302 (temporary), 307 (temporary preserve method), and 308 (permanent preserve method).

= Is visitor data collected? =

When logging is enabled, the plugin collects anonymized IP addresses (last octet removed for IPv4, last 80 bits for IPv6), user agent strings, and referer URLs. This data can be automatically purged using the log retention setting.

= Can I exclude certain subdomains? =

Yes, you can configure excluded subdomains in the settings. By default, common subdomains like www, mail, ftp, cpanel, and webmail are excluded.

= Does this work with page caching (Hummingbird, host static caches)? =

Redirect-type mappings work with page caching. When content is shown inline on a subdomain, the plugin sends no-cache headers on those subdomain requests so statistics stay accurate; main-domain caching is unaffected. If you prefer caching over exact counts, you can exclude those headers with a caching-plugin rule and treat counts as unique cached-page fills. Note that 301 domain redirects may be cached by browsers and edge caches regardless — use 302/307 while validating a new redirect.

= I use another domain-mapping plugin that has its own sunrise.php. Can they coexist? =

WordPress loads exactly one wp-content/sunrise.php. Do not overwrite an existing one (e.g. from WPMU DEV Domain Mapping or Mercator); instead append this plugin's sunrise.php contents to it. This plugin's sunrise code returns early when no configured domain matches, so it is safe to run after another handler.

= Do I still need to copy sunrise.php manually on multisite? =

No. Since 1.6.0 the plugin installs an auto-updating loader at wp-content/sunrise.php on activation, and the Domain Redirects settings section offers a one-click install that also adds the SUNRISE constant to wp-config.php when it is writable. Manual installation remains available as a fallback, and a sunrise.php owned by another plugin is never overwritten.

= Where do I configure domain redirects on multisite? =

On the main site only. The sunrise.php handler runs before the requested site is resolved and reads settings from the main site; domain redirects saved on subsites never fire at sunrise.

== Screenshots ==

1. Dashboard showing redirect statistics
2. Mapping management interface
3. Settings page
4. Detailed log viewer

== Changelog ==

= 1.6.0 =
* Requires WordPress 6.2+ (corrected from 6.0 — the plugin uses the %i identifier placeholder introduced in WordPress 6.2)
* Added: automatic sunrise handler installation on multisite — the plugin writes an auto-updating loader to wp-content/sunrise.php on activation, and the settings page offers one-click setup that can also add the SUNRISE constant to wp-config.php (existing third-party sunrise.php files are never overwritten)
* Fixed: "Keep path" and "Keep query string" options for domain redirects are now saved and honored consistently (previously the checkboxes were never persisted, and the non-sunrise redirect path ignored them)
* Fixed: sunrise.php is now included in built release packages
* Fixed: possible fatal error when a new network site was created outside wp-admin (WP-CLI, signup activation)
* Fixed: sunrise.php now anonymizes logged IP addresses and no longer trusts the spoofable X-Forwarded-For header by default (opt in with the SRC_TRUST_X_FORWARDED_FOR constant behind a trusted proxy)
* Added: SmartCrawl canonical URL support (wds_filter_canonical), alongside existing Yoast SEO and Rank Math support
* Fixed: duplicate canonical tags when an SEO plugin is active — the plugin now filters the SEO plugin's canonical instead of emitting its own
* Added: no-cache headers when serving content inline on a subdomain so page caches don't freeze statistics counting
* Added: log-insert throttling in sunrise.php to limit log flooding from pre-firewall traffic (SRC_SUNRISE_LOG_THROTTLE constant, default 10 seconds per domain)
* Improved: statistics now refresh the recorded target path when a mapping's target changes

= 1.5.0 =
* Added domain redirects (redirect entire alias domains to another URL) with statistics and logging
* Added sunrise.php for early domain redirect handling on multisite

= 1.4.0 =
* Added WordPress multisite support
* Per-site data model for mappings, statistics, and logs
* Automatic setup for new sites in network-activated installations
* Improved code quality and WordPress coding standards compliance

= 1.3.0 =
* Added support for home page redirects
* Added redirect code configuration per mapping
* Improved admin interface

= 1.2.0 =
* Added custom URL redirect type
* Enhanced statistics tracking
* Added log rotation with configurable retention

= 1.1.0 =
* Added detailed logging with privacy controls
* IP anonymization for GDPR compliance
* Performance improvements

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.6.0 =
Fixes domain-redirect path/query options, a possible multisite fatal, and sunrise.php packaging; adds SmartCrawl support and cache-safe statistics. If you copied sunrise.php to wp-content, re-copy the updated version.

= 1.4.0 =
Adds multisite support. If upgrading on a multisite network, consider network activating for automatic setup on all sites.

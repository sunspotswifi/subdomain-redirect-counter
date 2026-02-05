=== Subdomain Redirect Counter ===
Contributors: yourname
Tags: subdomain, redirect, counter, statistics, SEO
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.4.0
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

== Screenshots ==

1. Dashboard showing redirect statistics
2. Mapping management interface
3. Settings page
4. Detailed log viewer

== Changelog ==

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

= 1.4.0 =
Adds multisite support. If upgrading on a multisite network, consider network activating for automatic setup on all sites.

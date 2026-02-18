=== Cacheability ===

Contributors: dvershinin
Tags: cache, seo, 404, performance, varnish, nginx, cdn
Requires at least: 5.0
Requires PHP: 7.0
Tested up to: 6.9
Stable tag: 2.0.1
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.buymeacoffee.com/dvershinin

HTTP optimization for WordPress. Fixes soft 404 errors and adds smart cache headers.

== Description ==

Cacheability makes your WordPress site a better HTTP citizen, improving SEO and cache efficiency.

= Free Features =

**Soft 404 Fix**

WordPress returns HTTP 200 for empty search results, invalid tag pages, and empty category archives. Google marks these as "soft 404" errors in Search Console, hurting your SEO.

Cacheability fixes this by returning proper 404 status codes when:

* Search results are empty (`/?s=nonexistent`)
* Tag archives are empty (`/tag/nonexistent/`)
* Category archives are empty
* Author archives are empty

**Smart Cache-Control Headers**

Automatically adds `s-maxage` headers so Varnish, NGINX, and CDNs can cache your pages efficiently without affecting browser caching behavior.

* Search/404 pages: 1 hour cache
* All other pages: 1 year cache (your purge plugin handles invalidation)

= Cacheability Pro =

Upgrade to [Cacheability Pro](https://www.getpagespeed.com/web-apps/cacheability-pro) for advanced features:

* **Cache Warming** — Automatically warm pages after purging so visitors never hit cold cache
* **Conditional GET (304)** — Return 304 Not Modified for unchanged content, saving bandwidth
* **ESI Support** — Cache pages with dynamic nonces (comments, login forms)
* **Rate-Limit Safe** — Smart request queuing to avoid 429 errors
* **Sitemap Warming** — Warm all pages from sitemap after full purge
* **WP-CLI Commands** — `wp cacheability warm` and more
* **Priority Support** — Get help when you need it

[Get Cacheability Pro →](https://www.getpagespeed.com/web-apps/cacheability-pro)

== Installation ==

1. Upload to `/wp-content/plugins/cacheability/`
2. Activate the plugin through the 'Plugins' menu
3. That's it! No configuration needed.

The plugin works automatically. You can view the settings page under Settings → Cacheability.

== Frequently Asked Questions ==

= Is it compatible with caching plugins? =

Yes! Cacheability works alongside any caching solution including WP Super Cache, W3 Total Cache, WP Rocket, Varnish, NGINX FastCGI cache, and CDNs like Cloudflare.

= Does it slow down my site? =

No. Cacheability adds minimal overhead — it just sets proper HTTP headers and status codes.

= What's the difference between free and Pro? =

The free version fixes soft 404s and adds cache headers. Pro adds cache warming (automatically re-caches pages after purging), conditional GET responses (304), and ESI support for dynamic content.

= Do I need Varnish HTTP Purge plugin? =

For the free version, no. For Cacheability Pro's cache warming feature, we recommend [Varnish HTTP Purge](https://wordpress.org/plugins/varnish-http-purge/) or similar purge plugin.

== Screenshots ==

1. Settings page with Pro feature comparison

== Changelog ==

= 2.0.1 =
* Fixed duplicate Cache-Control headers when another plugin sets headers via PHP header() function

= 2.0.0 =
* Major update: Streamlined free version
* Cache warming, conditional GET, and ESI moved to Cacheability Pro
* Added settings page with Pro feature overview
* Improved soft 404 detection (now includes category and author archives)
* Code modernization and cleanup

= 1.1.7 =
* Fixed a PHP notice when used together with older versions of Varnish HTTP Purge plugin and WP-Rocket integrations

= 1.1.6 =
* Fixed a PHP fatal error when used together with older versions of Varnish HTTP Purge plugin and WP-Rocket integrations

= 1.1.3 =
* Fixed some PHP warnings

= 1.1.0 =
* Added cache warmup feature for updated content

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
Cache warming, conditional GET (304), and ESI features are now part of Cacheability Pro. The free version continues to provide soft 404 fixes and cache headers.

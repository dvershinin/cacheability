=== Cacheability ===

Contributors: dvershinin
Tags: caching, optimize, performance, pagespeed, Core Web Vitals, seo, speed, varnish
Requires at least: 4.6
Requires PHP: 7.0
Tested up to: 6.6
Stable tag: 1.1.5
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.buymeacoffee.com/dvershinin

== Description ==

Cacheability improves your website loading time by making it a well-behaved HTTP citizen.

== Plugin Features ==

= Conditional HTTP GET =

Cacheability adds conditional HTTP GET feature for WordPress posts. A repeat request to a post which wasn't modified, will result in a 304 HTTP response. It quickly tells the browser: "nothing new here" without sending the whole post all over again. This saves the bandwidth and increases performance on both ends.

= Fixes soft 404 errors =

WordPress emits soft 404s on empty search results or an invalid tag page, e.g., either `/?s=foo` or `/tag/bar` will always result in the HTTP 200 status code, irrespective of whether any entries were displayed there. Soft 404s are bad for you! Cacheability eliminates them by setting the proper 404 HTTP status upon empty search results or tags.

This improves your SEO ranking.

= Warming cache for updated content =

Every time you edit a WordPress post, your cache is cleared in many places. The post page is cleared, the homepage is cleared, the category, feeds, etc., etc. You edited just a *single* post or page, but your cache is cleared in *many* places!

This is cool because you don't want stale content on your website. But it's not cool to make your next visitors face slow pages!

Cacheability automatically warms up the pages which were purged from cache:

* It warms up purged caches as soon as you edit your content, via cron
* It warms up both Gzip and Brotli versions of cleared pages

All this allows for more happy visitors that hit your cache, and not slow backend!.

This feature requires [Proxy Cache Purge](https://wordpress.org/plugins/varnish-http-purge/) plugin.
Also, ensure WordPress cron is configured correctly.

== Frequently Asked Questions ==

= Is it compatible with Full Page Cache plugins? =

Yes, absolutely. Moreover, Cacheability adds the correct HTTP semantics making browsers and any external caches like Varnish to more efficiently cache your website's content.

== Changelog ==

= 1.1.3 =
* Fixed some PHP warnings

= 1.1.0 =
* Added cache warmup feature for updated content

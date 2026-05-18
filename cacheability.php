<?php
/**
 * Plugin Name: Cacheability
 * Plugin URI: https://wordpress.org/plugins/cacheability/
 * Description: HTTP optimization for WordPress. Fixes soft 404s and adds proper cache headers. Upgrade to Pro for cache warming, conditional GET, and ESI.
 * Version: 2.1.0
 * Author: Danila Vershinin
 * Author URI: https://www.getpagespeed.com/
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cacheability
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Tested up to: 6.7
 *
 * @package Cacheability
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Cacheability class (Free version).
 */
class Cacheability {

	/**
	 * Pro plugin URL.
	 */
	const PRO_URL = 'https://www.getpagespeed.com/web-apps/cacheability-pro';

	/**
	 * Single instance.
	 *
	 * @var Cacheability|null
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return Cacheability
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Skip features if Pro is installed - it handles everything.
		if ( class_exists( 'Cacheability_Pro' ) ) {
			return;
		}

		// Free features.
		add_action( 'wp', array( $this, 'fix_soft_404' ) );
		add_filter( 'wp_headers', array( $this, 'add_cache_headers' ), 100 );
		add_filter( 'wpseo_robots', array( $this, 'filter_wpseo_robots' ), 20 );
		add_filter( 'wp_robots', array( $this, 'filter_wp_robots' ), 20 );

		// Admin.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
			add_action( 'admin_notices', array( $this, 'show_pro_notice' ) );
			add_action( 'wp_ajax_cacheability_dismiss_pro_notice', array( $this, 'dismiss_pro_notice' ) );
		}
	}

	/**
	 * Fix soft 404 errors on empty search/tag/category pages.
	 *
	 * WordPress returns 200 OK for empty search results and invalid tag pages,
	 * which Google marks as "soft 404" errors in Search Console.
	 */
	public function fix_soft_404() {
		if ( is_search() && ! have_posts() ) {
			status_header( 404 );
			return;
		}

		if ( is_tag() && ! have_posts() ) {
			status_header( 404 );
			return;
		}

		if ( is_category() && ! have_posts() ) {
			status_header( 404 );
			return;
		}

		if ( is_author() && ! have_posts() ) {
			status_header( 404 );
			return;
		}
	}

	/**
	 * Whether the current request is an empty archive that should be marked noindex.
	 *
	 * Defense-in-depth complement to fix_soft_404(): if another plugin or theme
	 * forces HTTP 200 on these views, the noindex meta tag still prevents Google
	 * from flagging them as "Soft 404".
	 *
	 * @return bool
	 */
	private function is_empty_archive_or_search() {
		if ( is_search() ) {
			return true;
		}

		if ( is_tag() || is_category() || is_tax() ) {
			$obj = get_queried_object();
			if ( $obj instanceof WP_Term && 0 === (int) $obj->count ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Filter Yoast SEO's robots meta string for empty archives and search.
	 *
	 * @param string $robots Yoast's existing robots directive.
	 * @return string
	 */
	public function filter_wpseo_robots( $robots ) {
		return $this->is_empty_archive_or_search() ? 'noindex, follow' : $robots;
	}

	/**
	 * Filter WordPress core's wp_robots array for empty archives and search.
	 *
	 * Strips index plus the max-* hints (meaningless once noindex is set).
	 *
	 * @param array $robots Current robots directives.
	 * @return array
	 */
	public function filter_wp_robots( $robots ) {
		if ( ! $this->is_empty_archive_or_search() ) {
			return $robots;
		}

		unset( $robots['index'], $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
		$robots['noindex'] = true;
		$robots['follow']  = true;

		return $robots;
	}

	/**
	 * Add Cache-Control headers for proxy caches.
	 *
	 * Uses s-maxage to set shared cache TTL without affecting browser caching.
	 *
	 * @param array $headers Current headers.
	 * @return array Modified headers.
	 */
	public function add_cache_headers( $headers ) {
		// Don't override existing Cache-Control from wp_headers filter.
		if ( isset( $headers['Cache-Control'] ) ) {
			return $headers;
		}

		// Don't override Cache-Control set via header() by other plugins.
		if ( ! headers_sent() ) {
			foreach ( headers_list() as $header ) {
				if ( stripos( $header, 'Cache-Control:' ) === 0 ) {
					return $headers;
				}
			}
		}

		// Don't cache for logged-in users.
		if ( is_user_logged_in() ) {
			return $headers;
		}

		// Don't cache admin pages.
		if ( is_admin() ) {
			return $headers;
		}

		// Search/404 = short cache.
		if ( is_search() || is_404() ) {
			$headers['Cache-Control'] = 'max-age=60, s-maxage=3600';
			return $headers;
		}

		// Everything else = long cache (purge plugin handles invalidation).
		$headers['Cache-Control'] = 'max-age=0, s-maxage=31536000';

		return $headers;
	}

	/**
	 * Add settings page to admin menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Cacheability', 'cacheability' ),
			__( 'Cacheability', 'cacheability' ),
			'manage_options',
			'cacheability',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap" style="max-width: 700px;">
			<h1><?php esc_html_e( 'Cacheability', 'cacheability' ); ?></h1>

			<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-top: 20px;">
				<h2 style="margin-top: 0;"><?php esc_html_e( 'Active Features', 'cacheability' ); ?></h2>

				<table class="widefat striped" style="margin-top: 15px;">
					<tbody>
						<tr>
							<td><strong>✅ <?php esc_html_e( 'Soft 404 Fixes', 'cacheability' ); ?></strong></td>
							<td style="color: green;"><?php esc_html_e( 'Active', 'cacheability' ); ?></td>
						</tr>
						<tr>
							<td>
								<?php esc_html_e( 'Returns proper 404 status for empty search results, tags, categories, and author archives.', 'cacheability' ); ?>
							</td>
							<td></td>
						</tr>
						<tr>
							<td><strong>✅ <?php esc_html_e( 'Cache-Control Headers', 'cacheability' ); ?></strong></td>
							<td style="color: green;"><?php esc_html_e( 'Active', 'cacheability' ); ?></td>
						</tr>
						<tr>
							<td>
								<?php esc_html_e( 'Adds s-maxage headers so Varnish/CDN can cache efficiently.', 'cacheability' ); ?>
							</td>
							<td></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div style="background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
						border-radius: 8px; padding: 24px; margin-top: 20px; color: white;">
				<h2 style="margin: 0 0 15px; color: white;">
					⚡ <?php esc_html_e( 'Upgrade to Cacheability Pro', 'cacheability' ); ?>
				</h2>

				<p style="opacity: 0.95; margin-bottom: 20px;">
					<?php esc_html_e( 'Get cache warming, conditional GET (304), and ESI support.', 'cacheability' ); ?>
				</p>

				<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
					<tr>
						<td style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.2);">
							<?php esc_html_e( 'Cache Warming', 'cacheability' ); ?>
						</td>
						<td style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.2); text-align: right;">
							<span style="background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 3px;">Pro</span>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.2);">
							<?php esc_html_e( 'Conditional GET (304 responses)', 'cacheability' ); ?>
						</td>
						<td style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.2); text-align: right;">
							<span style="background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 3px;">Pro</span>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.2);">
							<?php esc_html_e( 'ESI Support (dynamic nonces)', 'cacheability' ); ?>
						</td>
						<td style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.2); text-align: right;">
							<span style="background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 3px;">Pro</span>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.2);">
							<?php esc_html_e( 'Rate-Limit Safe Warming', 'cacheability' ); ?>
						</td>
						<td style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.2); text-align: right;">
							<span style="background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 3px;">Pro</span>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.2);">
							<?php esc_html_e( 'WP-CLI Commands', 'cacheability' ); ?>
						</td>
						<td style="padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.2); text-align: right;">
							<span style="background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 3px;">Pro</span>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0;">
							<?php esc_html_e( 'Priority Support', 'cacheability' ); ?>
						</td>
						<td style="padding: 8px 0; text-align: right;">
							<span style="background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 3px;">Pro</span>
						</td>
					</tr>
				</table>

				<a href="<?php echo esc_url( self::PRO_URL ); ?>"
					class="button"
					style="background: #fff; color: #1e3a5f; border: none; padding: 10px 24px; font-weight: 600; font-size: 14px;"
					target="_blank">
					<?php esc_html_e( 'Get Cacheability Pro →', 'cacheability' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Show upgrade notice (dismissible).
	 */
	public function show_pro_notice() {
		// Don't show if Pro is installed.
		if ( class_exists( 'Cacheability_Pro' ) ) {
			return;
		}

		// Only show on specific pages.
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'settings_page_cacheability' ), true ) ) {
			return;
		}

		// Check if dismissed.
		if ( get_option( 'cacheability_pro_notice_dismissed' ) ) {
			return;
		}

		// Don't show on our own settings page (has its own upsell).
		if ( 'settings_page_cacheability' === $screen->id ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible" id="cacheability-pro-notice">
			<p>
				<strong>⚡ <?php esc_html_e( 'Cacheability Pro', 'cacheability' ); ?></strong> —
				<?php esc_html_e( 'Add cache warming, conditional GET (304), and ESI support.', 'cacheability' ); ?>
				<a href="<?php echo esc_url( self::PRO_URL ); ?>" target="_blank">
					<?php esc_html_e( 'Learn more →', 'cacheability' ); ?>
				</a>
			</p>
		</div>
		<script>
		jQuery(function($) {
			$(document).on('click', '#cacheability-pro-notice .notice-dismiss', function() {
				$.post(ajaxurl, { action: 'cacheability_dismiss_pro_notice' });
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler for dismissing the Pro notice.
	 */
	public function dismiss_pro_notice() {
		update_option( 'cacheability_pro_notice_dismissed', true );
		wp_die();
	}
}

/**
 * Initialize the plugin.
 *
 * @return Cacheability
 */
function cacheability() {
	return Cacheability::instance();
}

// Start the plugin.
add_action( 'plugins_loaded', 'cacheability', 5 );

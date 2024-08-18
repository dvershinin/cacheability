<?php
/*
Plugin Name: Cacheability
Description: Empowers WordPress with conditional HTTP GET and other cache features
Version: 1.1.5
Author: Danila Vershinin
Author URI: https://github.com/dvershinin
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

add_action('wp', function() {

    /**
     * Fix Soft 404s
     * WordPress emits soft 404 on empty search results or an invalid tag page, e.g.,
     * https://www.example.com/?s=foo *always* returns http status code 200
     * https://www.example.com/tag/nonexistent_shit returns http status code 200
     * This fixes WordPress and returns proper `404` header there
    **/

    if ( (is_search() || is_tag()) && !have_posts() ) {
        status_header(404);
    }

    /**
     * Conditional HTTP GET for posts
     **/

    if ( ! is_single() ) {
        return;
    }

    // previewing does not update post's last-modified but may display different content
    if ( is_preview() ) {
        return;
    }

    $post = get_queried_object();

	if (!$post) {
		return;
	}

    # use post's last modified date, unless there's a comment after modification
    $last_modified_gmt = $post->post_modified_gmt;

    if ($post->comment_count) {
        $last_comment_after_post_modified = get_comments([
            'post_id' => $post->ID,
            'orderby' => 'comment_date_gmt',
            'status' => 'approve',
            'number' => 1,
            'order' => 'DESC',
        ]);
        if ($last_comment_after_post_modified) {
            $last_modified_gmt = $last_comment_after_post_modified[0]->comment_date_gmt;
        }
    }

    $last_modified_ts = strtotime($last_modified_gmt);

    $client_last_modified = sanitize_text_field(empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? '' : trim($_SERVER['HTTP_IF_MODIFIED_SINCE']));

    // If string is empty, return 0. If not, attempt to parse into a timestamp.
    $client_modified_timestamp = $client_last_modified ? strtotime($client_last_modified) : 0;


    if ($client_modified_timestamp >= $last_modified_ts) {
        status_header(304);
        # remove any entity header when replying with 304
        foreach (headers_list() as $header) {
            $header = trim(explode(':', $header)[0]);
            header_remove($header);
        }
        exit();
    }

    $last_modified = gmdate('D, d M Y H:i:s', $last_modified_ts);
    $last_modified .= ' GMT';

    header('Last-Modified: ' . $last_modified);


});

# Warming pages upon purge
# proxy cache purge plugin calls this:
# do_action( 'after_purge_url', $parsed_url, $purgeme, $response, $headers );
# TODO hook onto "after_full_purge" and warm based on sitemap
# TODO chunk/spread URL warm over time to reduce CPU usage on smaller boxes

# Create action that will be scheduled upon purging a URL
add_action( 'cacheability_warm_event', function($url) {
    wp_remote_get(
        $url,
        array(
            'headers' => array(
                'accept-encoding' => 'br',
                'user-agent' => 'wp-proxy-cache-warmer-br',
            ),
        )
    );

    wp_remote_get(
        $url,
        array(
            'headers'   => array(
                'accept-encoding' => 'gzip',
                'user-agent' => 'wp-proxy-cache-warmer-gzip',
            ),
        )
    );
} );


# Schedule a warm event after purging a URL
add_action( 'after_purge_url', function ($parsed_url, $purgeme, $response, $headers) {
    if ($headers['X-Purge-Method'] != 'default') {
        return;
    }

    $parsed_url = str_replace( 'http://', 'https://', $parsed_url );

    wp_schedule_single_event( time(), 'cacheability_warm_event', array( $parsed_url ) );

}, $priority = 10, $accepted_args = 4 );


# Add Far Future Cache-Control header to all pages served by WordPress
# This only does it for shared caches, not for browsers
add_filter( 'wp_headers', function( $headers) {

    // Check if the Cache-Control header is already set.
    if ( ! isset( $headers['Cache-Control'] ) ) {
        // in search pages, set the s-maxage directive to 1 hour (in seconds).
        if ( is_search() ) {
            $headers['Cache-Control'] = 's-maxage=3600';
        } else {
            // Set the s-maxage directive to 1 year (in seconds).
            $headers['Cache-Control'] = 's-maxage=31536000';
        }
    }

    return $headers;
}, 100 );

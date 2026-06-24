<?php
/**
 * Plugin Name: Cacheability Test Control
 * Description: REST API endpoints for testing
 */

defined( 'ABSPATH' ) || exit;

// --- cacheability_skip opt-out fixtures (test_cache_headers.py) ---
// A dedicated page whose requests are flagged for the public opt-out filter, so the
// test can assert the plugin emits no Cache-Control. Keyed on the request path so it
// fires deterministically at wp_headers time.
add_filter( 'cacheability_skip', function ( $skip ) {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	if ( false !== strpos( $uri, 'cacheability-skip-test' ) ) {
		return true;
	}
	return $skip;
} );

add_action( 'init', function () {
	if ( function_exists( 'is_blog_installed' ) && ! is_blog_installed() ) {
		return;
	}
	if ( ! get_page_by_path( 'cacheability-skip-test' ) ) {
		wp_insert_post( array(
			'post_title'   => 'Cacheability Skip Test',
			'post_name'    => 'cacheability-skip-test',
			'post_content' => 'This page opts out of caching via the cacheability_skip filter.',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );
	}
} );

// Register REST API endpoints for testing.
add_action( 'rest_api_init', function() {
	// Create a post.
	register_rest_route( 'test/v1', '/post', array(
		'methods'             => 'POST',
		'callback'            => function( $request ) {
			$title   = $request->get_param( 'title' ) ?: 'Test Post ' . time();
			$content = $request->get_param( 'content' ) ?: 'Test content';

			$post_id = wp_insert_post( array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'post',
			) );

			if ( is_wp_error( $post_id ) ) {
				return new WP_Error( 'post_creation_failed', $post_id->get_error_message(), array( 'status' => 500 ) );
			}

			return array(
				'id'  => $post_id,
				'url' => get_permalink( $post_id ),
			);
		},
		'permission_callback' => '__return_true',
	) );

	// Create a category.
	register_rest_route( 'test/v1', '/category', array(
		'methods'             => 'POST',
		'callback'            => function( $request ) {
			$name = $request->get_param( 'name' ) ?: 'Test Category ' . time();
			$slug = $request->get_param( 'slug' ) ?: sanitize_title( $name );

			$term = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );

			if ( is_wp_error( $term ) ) {
				// Term might already exist.
				$existing = get_term_by( 'slug', $slug, 'category' );
				if ( $existing ) {
					return array(
						'id'  => $existing->term_id,
						'url' => get_term_link( $existing ),
					);
				}
				return new WP_Error( 'term_creation_failed', $term->get_error_message(), array( 'status' => 500 ) );
			}

			return array(
				'id'  => $term['term_id'],
				'url' => get_term_link( $term['term_id'], 'category' ),
			);
		},
		'permission_callback' => '__return_true',
	) );

	// Create a tag.
	register_rest_route( 'test/v1', '/tag', array(
		'methods'             => 'POST',
		'callback'            => function( $request ) {
			$name = $request->get_param( 'name' ) ?: 'Test Tag ' . time();
			$slug = $request->get_param( 'slug' ) ?: sanitize_title( $name );

			$term = wp_insert_term( $name, 'post_tag', array( 'slug' => $slug ) );

			if ( is_wp_error( $term ) ) {
				$existing = get_term_by( 'slug', $slug, 'post_tag' );
				if ( $existing ) {
					return array(
						'id'  => $existing->term_id,
						'url' => get_term_link( $existing ),
					);
				}
				return new WP_Error( 'term_creation_failed', $term->get_error_message(), array( 'status' => 500 ) );
			}

			return array(
				'id'  => $term['term_id'],
				'url' => get_term_link( $term['term_id'], 'post_tag' ),
			);
		},
		'permission_callback' => '__return_true',
	) );

	// Endpoint to simulate another plugin setting Cache-Control via header().
	register_rest_route( 'test/v1', '/header-conflict', array(
		'methods'             => 'GET',
		'callback'            => function() {
			header( 'Cache-Control: no-store, must-revalidate' );
			return array( 'ok' => true );
		},
		'permission_callback' => '__return_true',
	) );
} );










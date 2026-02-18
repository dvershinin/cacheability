<?php
/**
 * Plugin Name: Cacheability Test Control
 * Description: REST API endpoints for testing
 */

defined( 'ABSPATH' ) || exit;

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










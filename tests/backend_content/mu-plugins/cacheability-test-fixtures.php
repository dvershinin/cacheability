<?php
/**
 * Plugin Name: Cacheability Test Fixtures
 * Description: Creates test pages for ESI testing
 */

// Create test page on init (runs once, checks if page exists)
add_action( 'init', function() {
    // Check if our test page exists
    $test_page = get_page_by_path( 'esi-test-page' );
    if ( ! $test_page ) {
        // Create the test page
        $page_id = wp_insert_post( array(
            'post_title'   => 'ESI Test Page',
            'post_name'    => 'esi-test-page',
            'post_content' => '[cacheability_nonce_test]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
        
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            error_log( 'Cacheability test page created with ID: ' . $page_id );
        }
    }
}, 20 ); // Run after DB tables are ready

// Shortcode that outputs a form with nonces
add_shortcode( 'cacheability_nonce_test', function() {
    ob_start();
    ?>
    <div class="esi-test-container">
        <h2>Real WordPress Nonce Test</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'esi-test-action', 'esi-test-nonce' ); ?>
            <input type="submit" value="Submit Test Form">
        </form>
        <p>If ESI is working, the nonce field above should contain a real nonce value, not an ESI tag.</p>
    </div>
    <?php
    return ob_get_clean();
} );

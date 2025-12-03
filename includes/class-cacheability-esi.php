<?php

class Cacheability_ESI {

    /**
     * Initialize ESI functionality.
     */
    public static function init() {
        // Handle ESI requests
        if ( isset( $_GET['cacheability_esi'] ) ) {
            self::handle_esi_request();
        }

        // Add response header to advertise ESI support
        add_action( 'send_headers', array( __CLASS__, 'send_surrogate_control_header' ) );
    }

    /**
     * Setup environment for ESI request.
     */
    private static function handle_esi_request() {
        if ( ! defined( 'DOING_ESI' ) ) {
            define( 'DOING_ESI', true );
        }

        // Speed optimizations
        add_filter( 'override_load_textdomain', '__return_true' );
        add_filter( 'stylesheet', '__return_empty_string' );
        add_filter( 'template', '__return_empty_string' );
        add_filter( 'sidebars_widgets', '__return_empty_array' );

        if ( ! defined( 'DISABLE_WP_CRON' ) ) {
            define( 'DISABLE_WP_CRON', true );
        }

        // Remove output hooks
        add_action( 'plugins_loaded', function() {
            remove_all_actions( 'wp_head' );
            remove_all_actions( 'wp_footer' );
            remove_all_actions( 'wp_print_scripts' );
            remove_all_actions( 'wp_print_styles' );
            remove_all_actions( 'wp_enqueue_scripts' );
            remove_all_actions( 'admin_bar_menu' );
            remove_all_actions( 'wp_body_open' );
        }, 0 );

        // Process request
        add_action( 'init', array( __CLASS__, 'process_request' ), 0 );
    }

    /**
     * Output the ESI response.
     */
    public static function process_request() {
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );

        $type   = sanitize_key( $_GET['cacheability_esi'] );
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : -1;
        $name   = isset( $_GET['name'] ) ? sanitize_text_field( wp_unslash( $_GET['name'] ) ) : '_wpnonce';

        switch ( $type ) {
            case 'nonce':
                echo self::nonce_field_html( $action, $name, false );
                break;
            case 'nonce_value':
                echo esc_attr( wp_create_nonce( $action ) );
                break;
            default:
                status_header( 400 );
                echo 'Unknown ESI type';
        }
        exit;
    }

    /**
     * Check if request is from an ESI-capable proxy.
     */
    public static function is_capable() {
        if ( defined( 'CACHEABILITY_FORCE_ESI' ) && CACHEABILITY_FORCE_ESI ) {
            return true;
        }
        return isset( $_SERVER['HTTP_SURROGATE_CAPABILITY'] ) && 
               stripos( $_SERVER['HTTP_SURROGATE_CAPABILITY'], 'ESI/1.0' ) !== false;
    }

    /**
     * Send Surrogate-Control header.
     */
    public static function send_surrogate_control_header() {
        if ( self::is_capable() ) {
            header( 'Surrogate-Control: content="ESI/1.0"' );
        }
    }

    /**
     * Generate nonce field HTML.
     */
    public static function nonce_field_html( $action, $name, $referer = true ) {
        $name = esc_attr( $name );
        $html = '<input type="hidden" id="' . $name . '" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" />';
        
        if ( $referer ) {
            $html .= wp_referer_field( false );
        }
        
        return $html;
    }
}


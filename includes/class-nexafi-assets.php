<?php
/**
 * NexaFi Asset Manager
 *
 * Enqueues scoped CSS and vanilla JavaScript for frontend and Elementor embeds.
 *
 * @package NexaFi_Financing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NexaFi_Assets {

    /**
     * Singleton instance
     *
     * @var NexaFi_Assets|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return NexaFi_Assets
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
        add_action( 'template_redirect', array( $this, 'send_nocache_headers' ) );
    }

    /**
     * Send no-cache HTTP headers on NexaFi pages (homepage, /form/, and any page containing the shortcode)
     * so each visitor's browser fetches a fresh copy and avoids stale nonces or cached form markup.
     */
    public function send_nocache_headers() {
        if ( is_admin() ) {
            return;
        }

        global $post;
        $should_nocache = false;

        if ( is_front_page() || is_home() ) {
            $should_nocache = true;
        }

        $current_post = is_a( $post, 'WP_Post' ) ? $post : ( function_exists( 'get_queried_object' ) ? get_queried_object() : null );

        if ( is_a( $current_post, 'WP_Post' ) ) {
            if ( 'form' === $current_post->post_name ||
                 has_shortcode( $current_post->post_content, 'nexafi_financing_form' ) ||
                 has_shortcode( $current_post->post_content, 'nexafi_financing_application' ) ||
                 has_shortcode( $current_post->post_content, 'nexafi_contact_form' ) ) {
                $should_nocache = true;
            }
        }

        if ( function_exists( 'is_page' ) && is_page( 'form' ) ) {
            $should_nocache = true;
        }

        if ( $should_nocache ) {
            if ( ! defined( 'DONOTCACHEPAGE' ) ) {
                define( 'DONOTCACHEPAGE', true );
            }
            if ( function_exists( 'nocache_headers' ) ) {
                nocache_headers();
            }
            if ( ! headers_sent() ) {
                header( 'Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private', true );
                header( 'Pragma: no-cache', true );
                header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT', true );
            }
        }
    }

    /**
     * Register scripts & styles so they can be enqueued by shortcode or everywhere needed
     */
    public function register_frontend_assets() {
        // Inter & Caveat Google Fonts if not loaded by theme
        wp_register_style(
            'nexafi-google-fonts',
            'https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@1,600;1,700&display=swap',
            array(),
            NEXAFI_CF_VERSION
        );

        wp_register_style(
            'nexafi-frontend-css',
            plugins_url( 'assets/css/nexafi-frontend.css', dirname( __FILE__ ) ),
            array( 'nexafi-google-fonts' ),
            NEXAFI_CF_VERSION
        );

        wp_register_script(
            'nexafi-frontend-js',
            plugins_url( 'assets/js/nexafi-frontend.js', dirname( __FILE__ ) ),
            array(),
            NEXAFI_CF_VERSION,
            true
        );

        $form_page_url = get_option( 'nexafi_form_page_url', home_url( '/form/' ) );

        $db = NexaFi_DB::get_instance();
        $default_rep = $db->get_default_representative();
        $default_rep_id = ( $default_rep && ! empty( $default_rep->rep_id ) ) ? $default_rep->rep_id : '';
        $default_rep_name = ( $default_rep && ! empty( $default_rep->name ) ) ? $default_rep->name : '';

        wp_localize_script(
            'nexafi-frontend-js',
            'nexafi_obj',
            array(
                'ajax_url'         => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'nexafi_financing_nonce' ),
                'default_rep_id'   => $default_rep_id,
                'default_rep_name' => $default_rep_name,
                'home_url'         => home_url( '/' ),
                'return_url'       => esc_url( get_option( 'nexafi_return_url', '' ) ),
                'privacy_url'      => esc_url( get_option( 'nexafi_privacy_policy_url', '' ) ),
                'form_url'        => esc_url( $form_page_url ),
                'min_quote'       => floatval( get_option( 'nexafi_min_quote', 0.0 ) ),
                'max_quote'       => floatval( get_option( 'nexafi_max_quote', 50000.0 ) ),
                'i18n'            => array(
                    'verifying_rep'   => __( 'Verifying representative...', 'nexafi-plugin' ),
                    'submitting'      => __( 'Processing Application...', 'nexafi-plugin' ),
                    'submit_btn'      => __( 'Submit Application', 'nexafi-plugin' ),
                    'accepting'       => __( 'Finalizing...', 'nexafi-plugin' ),
                    'generic_error'   => __( 'An error occurred. Please check your details and try again.', 'nexafi-plugin' ),
                    'network_error'   => __( 'Unable to connect to the server. Please check your internet connection.', 'nexafi-plugin' ),
                    'consent_req'     => __( 'You must authorize information review to submit.', 'nexafi-plugin' ),
                    'required_field'  => __( 'This field is required.', 'nexafi-plugin' ),
                    'rep_not_found'   => __( 'Representative ID not found.', 'nexafi-plugin' ),
                ),
            )
        );

        // Always enqueue when on page containing the form, front page, or shortcode
        global $post;
        $should_enqueue = false;

        if ( is_front_page() || is_home() ) {
            $should_enqueue = true;
        }

        if ( is_a( $post, 'WP_Post' ) ) {
            if ( has_shortcode( $post->post_content, 'nexafi_financing_form' ) ||
                 has_shortcode( $post->post_content, 'nexafi_financing_application' ) ||
                 has_shortcode( $post->post_content, 'nexafi_contact_form' ) ||
                 'form' === $post->post_name ) {
                $should_enqueue = true;
            }
        }

        if ( $should_enqueue ) {
            wp_enqueue_style( 'nexafi-frontend-css' );
            wp_enqueue_script( 'nexafi-frontend-js' );
        }
    }
}

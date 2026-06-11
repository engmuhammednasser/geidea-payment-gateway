<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GPG_Booking {

    public function __construct() {
        add_shortcode( 'geidea_car_payment', array( $this, 'render_payment_button' ) );
        add_shortcode( 'geidea_return_page', array( $this, 'render_return_page' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function enqueue_scripts() {
        wp_register_style(
            'geidea-public-css',
            GPG_PLUGIN_URL . 'assets/css/geidea-public.css',
            array(),
            GPG_VERSION
        );

        wp_register_script( 
            'geidea-redirect-js', 
            GPG_PLUGIN_URL . 'assets/js/geidea-redirect.js', 
            array( 'jquery' ), 
            GPG_VERSION, 
            true 
        );

        wp_localize_script( 'geidea-redirect-js', 'gbgData', array(
            'rest_url' => esc_url_raw( rest_url( 'geidea-booking/v1/create-session' ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'loading_text' => __( 'جاري تحويلك إلى بوابة الدفع...', 'geidea-payment-gateway' ),
            'error_text'   => __( 'تعذر بدء عملية الدفع، من فضلك حاول مرة أخرى أو تواصل معنا.', 'geidea-payment-gateway' )
        ) );
    }

    public function render_payment_button( $atts ) {
        $atts = shortcode_atts( array(
            'booking_id' => 0,
        ), $atts, 'geidea_car_payment' );

        $booking_id = absint( $atts['booking_id'] );

        if ( ! $booking_id ) {
            return '<p>' . __( 'Invalid booking ID.', 'geidea-payment-gateway' ) . '</p>';
        }

        if ( ! GPG_Settings::get_setting( 'GPG_enabled' ) ) {
            return '';
        }

        wp_enqueue_style( 'geidea-public-css' );
        wp_enqueue_script( 'geidea-redirect-js' );

        ob_start();
        include GPG_PLUGIN_DIR . 'templates/payment-button.php';
        return ob_get_clean();
    }

    public function render_return_page() {
        ob_start();
        include GPG_PLUGIN_DIR . 'templates/return-page.php';
        return ob_get_clean();
    }
}


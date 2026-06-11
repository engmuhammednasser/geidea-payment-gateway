<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GPG_Booking {

    public function __construct() {
        add_shortcode( 'geidea_car_payment', array( $this, 'render_payment_button' ) );
        add_shortcode( 'geidea_return_page', array( $this, 'render_return_page' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'ashhalan_process_payment_redirect', array( $this, 'process_theme_payment' ), 10, 8 );
        add_action( 'template_redirect', array( $this, 'handle_global_redirect' ) );
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

        wp_localize_script( 'geidea-redirect-js', 'gpgData', array(
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

    public function process_theme_payment( $response, $payment_method, $order_id, $data, $customer, $qty, $actual_daily_price, $car_id ) {
        if ( $response !== false ) {
            return $response; // Already handled
        }

        if ( $payment_method !== 'geidea' && $payment_method !== 'visa' ) {
            return $response;
        }

        update_post_meta( $order_id, '_payment_method', 'geidea' );
        update_post_meta( $order_id, '_order_status', 'awaiting_payment' );

        $api_client = new GPG_API_Client();
        $mode = $api_client->get_mode();

        $amount = floatval( $data['total'] );
        $currency = 'SAR';
        $timestamp = current_time( 'Y-m-d H:i:s' );
        
        $prefix = $mode === 'live' ? 'LIVE-KSA-CAR-' : 'TEST-KSA-CAR-';
        $merchantReferenceId = $prefix . $order_id . '-' . time();

        $public_key = $api_client->get_public_key();
        $api_password = $api_client->get_api_password();
        
        // Automatically use the home URL as the secure return point
        $returnUrl = add_query_arg( 'booking_id', $order_id, home_url( '/' ) );

        $callbackUrl = get_rest_url( null, 'geidea-booking/v1/callback' );

        $signature = GPG_Signature::generate( $public_key, $amount, $currency, $merchantReferenceId, $api_password, $timestamp );

        $payload = array(
            'amount' => $amount,
            'currency' => $currency,
            'timestamp' => $timestamp,
            'merchantReferenceId' => $merchantReferenceId,
            'signature' => $signature,
            'callbackUrl' => $callbackUrl,
            'returnUrl' => $returnUrl,
            'language' => 'ar',
            'paymentOperation' => 'Pay',
            'customer' => array(
                'email' => isset($customer['email']) ? sanitize_email($customer['email']) : '',
                'firstName' => isset($customer['full_name']) ? sanitize_text_field($customer['full_name']) : '',
                'phoneNumber' => isset($customer['phone']) ? sanitize_text_field($customer['phone']) : '',
            ),
            'order' => array(
                'items' => array(
                    array(
                        'merchantItemId' => 'car-' . $car_id,
                        'name' => isset($data['car_name']) ? sanitize_text_field($data['car_name']) : 'Car Rental',
                        'count' => $qty,
                        'price' => $actual_daily_price,
                        'sku' => strval($car_id)
                    )
                )
            )
        );

        $api_response = $api_client->create_session( $payload );

        if ( is_wp_error( $api_response ) ) {
            return array(
                'success' => false,
                'message' => 'Geidea Error: ' . $api_response->get_error_message()
            );
        }

        $session_id = sanitize_text_field( $api_response['session']['id'] );
        $checkout_url = $api_client->get_checkout_base_url() . rawurlencode( $session_id );

        return array(
            'success' => true,
            'data'    => array(
                'message'      => 'Redirecting to Geidea...',
                'order_id'     => $order_id,
                'redirect'     => true,
                'redirect_url' => $checkout_url,
            )
        );
    }

    public function handle_global_redirect() {
        if ( isset($_GET['responseCode']) ) {
            $response_code = sanitize_text_field($_GET['responseCode']);
            $order_id = isset($_GET['booking_id']) ? sanitize_text_field($_GET['booking_id']) : '';

            $redirect_url = home_url('/');

            if ($response_code === '000') {
                $redirect_url = GPG_Settings::get_setting('GPG_success_url');
            } elseif ($response_code === '010') {
                $redirect_url = GPG_Settings::get_setting('GPG_cancel_url');
                if ( $order_id ) wp_delete_post( $order_id, true );
            } else {
                $redirect_url = GPG_Settings::get_setting('GPG_failed_url');
                if ( $order_id ) wp_delete_post( $order_id, true );
            }

            if ( !empty($redirect_url) ) {
                wp_redirect( $redirect_url );
                exit;
            }
        }
    }
}


<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GPG_REST {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'geidea-booking/v1', '/create-session', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'create_session' ),
            'permission_callback' => array( $this, 'check_nonce' ),
        ) );

        register_rest_route( 'geidea-booking/v1', '/callback', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'handle_callback' ),
            'permission_callback' => '__return_true', // Webhook is public
        ) );
    }

    public function check_nonce( $request ) {
        $nonce = $request->get_param( 'nonce' ) ? $request->get_param( 'nonce' ) : $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'geidea-payment-gateway' ), array( 'status' => 403 ) );
        }
        return true;
    }

    public function create_session( WP_REST_Request $request ) {
        if ( ! GPG_Settings::get_setting( 'GPG_enabled' ) ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => __( 'Gateway is disabled.', 'geidea-payment-gateway' )
            ) );
        }

        $booking_id = absint( $request->get_param( 'booking_id' ) );
        if ( ! $booking_id ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => __( 'Booking ID is required.', 'geidea-payment-gateway' )
            ) );
        }

        // Prevent duplicate requests
        $lock_key = 'GPG_payment_lock_' . $booking_id;
        if ( get_transient( $lock_key ) ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => __( 'Payment process is already in progress. Please wait.', 'geidea-payment-gateway' )
            ) );
        }
        set_transient( $lock_key, true, 60 );

        // Get context from filter
        $context = apply_filters( 'GPG_booking_context', array(), $booking_id );
        
        if ( empty( $context ) || ! isset( $context['amount'] ) ) {
            delete_transient( $lock_key );
            return rest_ensure_response( array(
                'success' => false,
                'message' => __( 'Invalid booking data.', 'geidea-payment-gateway' )
            ) );
        }

        $api_client = new GPG_API_Client();
        $mode = $api_client->get_mode();

        $amount = (float) $context['amount'];
        $currency = GPG_Settings::get_setting( 'GPG_currency', 'SAR' );
        $timestamp = current_time( 'Y-m-d H:i:s' );
        
        // Build reference ID based on mode
        $prefix = $mode === 'live' ? 'LIVE-KSA-CAR-' : 'TEST-KSA-CAR-';
        $merchantReferenceId = $prefix . $booking_id . '-' . time();

        $public_key = $api_client->get_public_key();
        $api_password = $api_client->get_api_password();
        $language = GPG_Settings::get_setting( 'GPG_language', 'ar' );
        
        // Use pending verification page as returnUrl because returnUrl shouldn't confirm payment
        $returnUrl = GPG_Settings::get_setting( 'GPG_pending_url' );
        if ( empty( $returnUrl ) ) {
            $returnUrl = home_url( '/' ); // Fallback
        }
        // Add booking_id parameter
        $returnUrl = add_query_arg( 'booking_id', $booking_id, $returnUrl );

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
            'language' => $language,
            'paymentOperation' => 'Pay',
        );

        if ( isset( $context['customer'] ) && is_array( $context['customer'] ) ) {
            $payload['customer'] = $context['customer'];
        }

        if ( isset( $context['items'] ) && is_array( $context['items'] ) ) {
            $payload['order'] = array( 'items' => $context['items'] );
        }

        // Action before sending
        do_action( 'GPG_before_session_create', $booking_id, $merchantReferenceId, $mode );

        $response = $api_client->create_session( $payload );

        delete_transient( $lock_key );

        if ( is_wp_error( $response ) ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => __( 'تعذر بدء عملية الدفع، من فضلك حاول مرة أخرى أو تواصل معنا.', 'geidea-payment-gateway' )
            ) );
        }

        $session_id = sanitize_text_field( $response['session']['id'] );
        $checkout_base_url = $api_client->get_checkout_base_url();
        $checkout_url = $checkout_base_url . rawurlencode( $session_id );

        // Save reference to DB (pseudo-action)
        do_action( 'GPG_session_created', $booking_id, $merchantReferenceId, $session_id );

        return rest_ensure_response( array(
            'success' => true,
            'checkout_url' => $checkout_url,
            'booking_id' => $booking_id,
            'merchant_reference_id' => $merchantReferenceId
        ) );
    }

    public function handle_callback( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        
        if ( empty( $payload ) ) {
            return new WP_REST_Response( 'Empty payload', 400 );
        }

        // Mask before logging
        $log_payload = $payload;
        if ( isset( $log_payload['source'] ) ) {
            $log_payload['source'] = '***masked***';
        }
        GPG_Logger::debug( 'Webhook Received: ' . wp_json_encode( $log_payload ) );

        $merchantReferenceId = sanitize_text_field( $payload['merchantReferenceId'] ?? '' );
        if ( empty( $merchantReferenceId ) ) {
            return new WP_REST_Response( 'Missing Reference', 400 );
        }

        // Determine mode from reference
        $is_test = strpos( $merchantReferenceId, 'TEST-KSA-CAR-' ) === 0;
        $is_live = strpos( $merchantReferenceId, 'LIVE-KSA-CAR-' ) === 0;

        if ( ! $is_test && ! $is_live ) {
            return new WP_REST_Response( 'Invalid Reference Format', 400 );
        }

        // Extract booking_id
        $prefix = $is_live ? 'LIVE-KSA-CAR-' : 'TEST-KSA-CAR-';
        $rest_of_ref = str_replace( $prefix, '', $merchantReferenceId );
        $parts = explode( '-', $rest_of_ref );
        $booking_id = absint( $parts[0] );

        // Extract values
        $responseCode = isset( $payload['responseCode'] ) ? sanitize_text_field( $payload['responseCode'] ) : '';
        $detailedResponseCode = isset( $payload['detailedResponseCode'] ) ? sanitize_text_field( $payload['detailedResponseCode'] ) : '';
        $amount = isset( $payload['amount'] ) ? (float) $payload['amount'] : 0;
        $currency = isset( $payload['currency'] ) ? sanitize_text_field( $payload['currency'] ) : '';

        // Validate Currency
        $expected_currency = GPG_Settings::get_setting( 'GPG_currency', 'SAR' );
        if ( $currency !== $expected_currency ) {
            GPG_Logger::error( "Webhook validation failed: Currency is not $expected_currency ($currency)." );
            return new WP_REST_Response( 'Invalid currency', 400 );
        }

        // Validate Amount against DB
        $context = apply_filters( 'GPG_booking_context', array(), $booking_id );
        if ( ! empty( $context ) ) {
            $db_amount = round( (float) $context['amount'], 2 );
            $cb_amount = round( $amount, 2 );
            if ( $db_amount !== $cb_amount ) {
                GPG_Logger::error( "Webhook validation failed for booking $booking_id. Amount mismatch (DB: $db_amount, CB: $cb_amount)." );
                return new WP_REST_Response( 'Amount mismatch', 400 );
            }
        }

        // Verify Signature
        if ( ! isset( $payload['signature'] ) || empty( $payload['signature'] ) ) {
            GPG_Logger::error( "Webhook validation failed: Missing signature." );
            return new WP_REST_Response( 'Missing signature', 400 );
        }

        $api_client = new GPG_API_Client();
        
        $current_mode = $api_client->get_mode();
        $hook_mode = $is_live ? 'live' : 'sandbox';
        
        if ( $current_mode !== $hook_mode ) {
            GPG_Logger::error( "Webhook mode mismatch. Site is $current_mode, webhook is $hook_mode." );
            return new WP_REST_Response( 'Mode mismatch', 400 );
        }

        $expected_signature = GPG_Signature::generate( 
            $api_client->get_public_key(), 
            $amount, 
            $currency, 
            $merchantReferenceId, 
            $api_client->get_api_password(), 
            $payload['timestamp'] ?? '' 
        );

        $is_signature_valid = hash_equals( $expected_signature, $payload['signature'] );

        // Sometimes the orderId is used instead of merchantReferenceId in Geidea callbacks. 
        if ( ! $is_signature_valid && isset( $payload['orderId'] ) ) {
            $expected_signature_alt = GPG_Signature::generate( 
                $api_client->get_public_key(), 
                $amount, 
                $currency, 
                $payload['orderId'], 
                $api_client->get_api_password(), 
                $payload['timestamp'] ?? '' 
            );
            $is_signature_valid = hash_equals( $expected_signature_alt, $payload['signature'] );
        }

        if ( ! $is_signature_valid ) {
            GPG_Logger::error( "Webhook validation failed: Invalid signature." );
            return new WP_REST_Response( 'Invalid signature', 400 );
        }

        // Decide status
        if ( $responseCode === '000' && $detailedResponseCode === '000' ) {
            // Payment success
            $status = $is_live ? 'paid' : 'paid_test';
            GPG_Logger::info( "Payment successful for booking $booking_id. Status: $status. Ref: $merchantReferenceId" );
            do_action( 'GPG_payment_completed', $booking_id, $log_payload, $status );
        } else {
            // Payment failed
            $status = 'payment_failed';
            GPG_Logger::info( "Payment failed for booking $booking_id. Status: $status. Ref: $merchantReferenceId" );
            do_action( 'GPG_payment_failed', $booking_id, $log_payload, $status );
        }

        return new WP_REST_Response( 'OK', 200 );
    }
}


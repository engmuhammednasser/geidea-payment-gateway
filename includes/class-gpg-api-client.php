<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GPG_API_Client {

    private $mode;
    private $public_key;
    private $api_password;
    private $api_base_url;
    private $checkout_base_url;
    
    public function __construct() {
        $this->mode = GPG_Settings::get_setting( 'GPG_mode', 'sandbox' );
        
        if ( $this->mode === 'live' ) {
            $this->public_key = GPG_Settings::get_setting( 'GPG_live_public_key' );
            $this->api_password = GPG_Settings::get_setting( 'GPG_live_api_password' );
            $this->api_base_url = GPG_Settings::get_setting( 'GPG_live_api_base_url', 'https://api.ksamerchant.geidea.net/' );
            $this->checkout_base_url = GPG_Settings::get_setting( 'GPG_live_checkout_base_url', 'https://www.ksamerchant.geidea.net/hpp/checkout/?' );
        } else {
            $this->public_key = GPG_Settings::get_setting( 'GPG_sandbox_public_key' );
            $this->api_password = GPG_Settings::get_setting( 'GPG_sandbox_api_password' );
            $this->api_base_url = GPG_Settings::get_setting( 'GPG_sandbox_api_base_url', 'https://api.ksamerchant.geidea.net/' );
            $this->checkout_base_url = GPG_Settings::get_setting( 'GPG_sandbox_checkout_base_url', 'https://www.ksamerchant.geidea.net/hpp/checkout/?' );
        }
    }

    public function get_api_base_url() {
        return trailingslashit( $this->api_base_url );
    }

    public function get_checkout_base_url() {
        return $this->checkout_base_url;
    }
    
    public function get_public_key() {
        return $this->public_key;
    }
    
    public function get_api_password() {
        return $this->api_password;
    }
    
    public function get_mode() {
        return $this->mode;
    }

    public function create_session( $payload ) {
        $url = $this->get_api_base_url() . 'payment-intent/api/v2/direct/session';
        
        if ( empty( $this->public_key ) || empty( $this->api_password ) ) {
            return new WP_Error( 'missing_credentials', __( 'Missing API credentials.', 'geidea-payment-gateway' ) );
        }

        $auth_header = 'Basic ' . base64_encode( $this->public_key . ':' . $this->api_password );

        $args = array(
            'body'        => wp_json_encode( $payload ),
            'timeout'     => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(
                'Authorization' => $auth_header,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
        );

        // Mask sensitive customer data before logging
        $log_payload = $payload;
        if ( isset($log_payload['customer']) ) {
            $log_payload['customer'] = '***masked***';
        }

        GPG_Logger::debug( 'Create Session Request URL (' . $this->mode . '): ' . $url );
        GPG_Logger::debug( 'Create Session Request Body: ' . wp_json_encode( $log_payload ) );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            GPG_Logger::error( 'Create Session Request Error: ' . $error_message );
            return new WP_Error( 'GPG_api_error', __( 'Failed to connect to Geidea API.', 'geidea-payment-gateway' ), array( 'details' => $error_message ) );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        $decoded_body = json_decode( $response_body, true );
        
        // Log response but mask sessionId if exists
        $log_response = $decoded_body;
        if ( isset( $log_response['session']['id'] ) ) {
            $log_response['session']['id'] = substr( $log_response['session']['id'], 0, 8 ) . '***';
        }
        GPG_Logger::debug( 'Create Session Response Code: ' . $response_code );
        GPG_Logger::debug( 'Create Session Response Body: ' . wp_json_encode( $log_response ) );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'GPG_api_error', __( 'Invalid JSON response from Geidea', 'geidea-payment-gateway' ) );
        }

        if ( $response_code >= 200 && $response_code < 300 && isset( $decoded_body['session']['id'] ) ) {
            return $decoded_body;
        }

        $error_msg = isset( $decoded_body['responseMessage'] ) ? $decoded_body['responseMessage'] : __( 'Unknown error from Geidea', 'geidea-payment-gateway' );
        return new WP_Error( 'GPG_api_error', $error_msg, $decoded_body );
    }
}


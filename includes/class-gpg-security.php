<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GPG_Security {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_test_tools_page' ) );
    }

    public function add_test_tools_page() {
        add_submenu_page(
            'geidea_gateway_settings',
            __( 'Geidea Test Tools', 'geidea-payment-gateway' ),
            __( 'Test Tools', 'geidea-payment-gateway' ),
            'manage_options',
            'geidea_test_tools',
            array( $this, 'render_test_tools_page' )
        );
    }

    public function render_test_tools_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $api_client = new GPG_API_Client();
        $mode = $api_client->get_mode();
        $api_base = $api_client->get_api_base_url();
        $checkout_base = $api_client->get_checkout_base_url();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Geidea Test Tools', 'geidea-payment-gateway' ); ?></h1>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Current Mode', 'geidea-payment-gateway' ); ?></th>
                    <td><strong><?php echo esc_html( strtoupper( $mode ) ); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Current API Base URL', 'geidea-payment-gateway' ); ?></th>
                    <td><code><?php echo esc_html( $api_base ); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Current Hosted Checkout URL', 'geidea-payment-gateway' ); ?></th>
                    <td><code><?php echo esc_html( $checkout_base ); ?></code></td>
                </tr>
            </table>

            <hr />

            <?php if ( $mode === 'sandbox' ) : ?>
                <h2><?php esc_html_e( 'Test Connection & Create Session', 'geidea-payment-gateway' ); ?></h2>
                <p><?php esc_html_e( 'Click the button below to simulate a test session creation of 1.00 SAR.', 'geidea-payment-gateway' ); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field( 'GPG_test_session', 'GPG_test_nonce' ); ?>
                    <input type="submit" name="GPG_run_test" class="button button-primary" value="<?php esc_attr_e( 'Create Test Session', 'geidea-payment-gateway' ); ?>" />
                </form>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p><strong><?php esc_html_e( 'WARNING:', 'geidea-payment-gateway' ); ?></strong> <?php esc_html_e( 'You are in LIVE mode. Creating a test session here will send real requests. Switch to Sandbox to use test tools safely.', 'geidea-payment-gateway' ); ?></p>
                </div>
            <?php endif; ?>

            <?php
            if ( isset( $_POST['GPG_run_test'] ) && wp_verify_nonce( $_POST['GPG_test_nonce'], 'GPG_test_session' ) && $mode === 'sandbox' ) {
                $this->run_test_session( $api_client );
            }
            ?>
        </div>
        <?php
    }

    private function run_test_session( $api_client ) {
        $amount = 1.00;
        $currency = GPG_Settings::get_setting( 'GPG_currency', 'SAR' );
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $merchantReferenceId = 'TEST-KSA-CAR-TEST-' . time();
        $public_key = $api_client->get_public_key();
        $api_password = $api_client->get_api_password();
        
        $signature = GPG_Signature::generate( $public_key, $amount, $currency, $merchantReferenceId, $api_password, $timestamp );
        
        $payload = array(
            'amount' => $amount,
            'currency' => $currency,
            'timestamp' => $timestamp,
            'merchantReferenceId' => $merchantReferenceId,
            'signature' => $signature,
            'callbackUrl' => get_rest_url( null, 'geidea-booking/v1/callback' ),
            'returnUrl' => home_url( '/' ),
            'language' => GPG_Settings::get_setting( 'GPG_language', 'ar' ),
            'paymentOperation' => 'Pay',
            'customer' => array(
                'email' => 'test@example.com',
                'firstName' => 'Test',
                'lastName' => 'User',
                'phoneNumber' => '500000000',
                'phonecountrycode' => '+966'
            )
        );

        echo '<h3>' . esc_html__( 'Request Payload (Masked):', 'geidea-payment-gateway' ) . '</h3>';
        $log_payload = $payload;
        $log_payload['customer'] = '***masked***';
        echo '<pre>' . esc_html( wp_json_encode( $log_payload, JSON_PRETTY_PRINT ) ) . '</pre>';

        $response = $api_client->create_session( $payload );

        echo '<h3>' . esc_html__( 'Response:', 'geidea-payment-gateway' ) . '</h3>';
        if ( is_wp_error( $response ) ) {
            echo '<pre style="color:red;">' . esc_html( print_r( $response->get_error_message(), true ) ) . '</pre>';
        } else {
            $log_response = $response;
            if ( isset( $log_response['session']['id'] ) ) {
                $log_response['session']['id'] = substr( $log_response['session']['id'], 0, 8 ) . '***';
            }
            echo '<pre style="color:green;">' . esc_html( wp_json_encode( $log_response, JSON_PRETTY_PRINT ) ) . '</pre>';
            
            if ( isset( $response['session']['id'] ) ) {
                $checkout_url = $api_client->get_checkout_base_url() . rawurlencode( $response['session']['id'] );
                echo '<p><a href="' . esc_url( $checkout_url ) . '" target="_blank" class="button">' . esc_html__( 'Open Checkout (New Tab for Test Only)', 'geidea-payment-gateway' ) . '</a></p>';
            }
        }
    }
}


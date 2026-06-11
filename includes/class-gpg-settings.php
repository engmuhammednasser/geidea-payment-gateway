<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GPG_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
    }

    public function add_settings_page() {
        add_menu_page(
            __( 'Geidea Gateway', 'geidea-payment-gateway' ),
            __( 'Geidea Gateway', 'geidea-payment-gateway' ),
            'manage_options',
            'geidea_gateway_settings',
            array( $this, 'render_settings_page' ),
            'dashicons-money-alt',
            56
        );
    }

    public function register_settings() {
        register_setting( 'GPG_settings_group', 'GPG_settings', array( $this, 'sanitize_settings' ) );

        // General Settings
        add_settings_section( 'GPG_general_section', __( 'General Settings', 'geidea-payment-gateway' ), null, 'geidea_gateway_settings' );
        add_settings_field( 'GPG_enabled', __( 'Enable Gateway', 'geidea-payment-gateway' ), array( $this, 'render_checkbox' ), 'geidea_gateway_settings', 'GPG_general_section', array( 'id' => 'GPG_enabled' ) );
        add_settings_field( 'GPG_mode', __( 'Mode', 'geidea-payment-gateway' ), array( $this, 'render_select' ), 'geidea_gateway_settings', 'GPG_general_section', array( 
            'id' => 'GPG_mode',
            'options' => array(
                'sandbox' => 'Sandbox/Test',
                'live' => 'Live/Production',
            )
        ) );
        add_settings_field( 'GPG_currency', __( 'Default Currency', 'geidea-payment-gateway' ), array( $this, 'render_select' ), 'geidea_gateway_settings', 'GPG_general_section', array( 
            'id' => 'GPG_currency',
            'options' => array(
                'SAR' => 'SAR',
                'USD' => 'USD',
                'EUR' => 'EUR',
                'GBP' => 'GBP',
                'AED' => 'AED'
            )
        ) );
        add_settings_field( 'GPG_language', __( 'Default Language', 'geidea-payment-gateway' ), array( $this, 'render_select' ), 'geidea_gateway_settings', 'GPG_general_section', array( 
            'id' => 'GPG_language',
            'options' => array(
                'ar' => 'Arabic (ar)',
                'en' => 'English (en)'
            )
        ) );
        add_settings_field( 'GPG_debug_log', __( 'Enable Debug Logging', 'geidea-payment-gateway' ), array( $this, 'render_checkbox' ), 'geidea_gateway_settings', 'GPG_general_section', array( 'id' => 'GPG_debug_log' ) );
        add_settings_field( 'GPG_github_token', __( 'GitHub Token (For Updates)', 'geidea-payment-gateway' ), array( $this, 'render_text' ), 'geidea_gateway_settings', 'GPG_general_section', array( 'id' => 'GPG_github_token' ) );

        // KSA Settings
        add_settings_section( 'GPG_ksa_section', __( 'KSA Settings', 'geidea-payment-gateway' ), null, 'geidea_gateway_settings' );
        add_settings_field( 'GPG_region', __( 'Region', 'geidea-payment-gateway' ), array( $this, 'render_readonly_text' ), 'geidea_gateway_settings', 'GPG_ksa_section', array( 'id' => 'GPG_region', 'default' => 'KSA' ) );
        add_settings_field( 'GPG_phone_code', __( 'Phone Country Code', 'geidea-payment-gateway' ), array( $this, 'render_readonly_text' ), 'geidea_gateway_settings', 'GPG_ksa_section', array( 'id' => 'GPG_phone_code', 'default' => '+966' ) );
        add_settings_field( 'GPG_customer_country', __( 'Customer Country', 'geidea-payment-gateway' ), array( $this, 'render_readonly_text' ), 'geidea_gateway_settings', 'GPG_ksa_section', array( 'id' => 'GPG_customer_country', 'default' => 'SAU' ) );

        // Sandbox Settings
        add_settings_section( 'GPG_sandbox_section', __( 'Sandbox Settings', 'geidea-payment-gateway' ), null, 'geidea_gateway_settings' );
        add_settings_field( 'GPG_sandbox_public_key', __( 'Sandbox Merchant Public Key', 'geidea-payment-gateway' ), array( $this, 'render_text' ), 'geidea_gateway_settings', 'GPG_sandbox_section', array( 'id' => 'GPG_sandbox_public_key' ) );
        add_settings_field( 'GPG_sandbox_api_password', __( 'Sandbox API Password', 'geidea-payment-gateway' ), array( $this, 'render_password' ), 'geidea_gateway_settings', 'GPG_sandbox_section', array( 'id' => 'GPG_sandbox_api_password' ) );
        add_settings_field( 'GPG_sandbox_api_base_url', __( 'Sandbox API Base URL', 'geidea-payment-gateway' ), array( $this, 'render_text' ), 'geidea_gateway_settings', 'GPG_sandbox_section', array( 'id' => 'GPG_sandbox_api_base_url', 'default' => 'https://api.ksamerchant.geidea.net/' ) );
        add_settings_field( 'GPG_sandbox_checkout_base_url', __( 'Sandbox Hosted Checkout Base URL', 'geidea-payment-gateway' ), array( $this, 'render_text' ), 'geidea_gateway_settings', 'GPG_sandbox_section', array( 'id' => 'GPG_sandbox_checkout_base_url', 'default' => 'https://www.ksamerchant.geidea.net/hpp/checkout/?' ) );

        // Live Settings
        add_settings_section( 'GPG_live_section', __( 'Live Settings', 'geidea-payment-gateway' ), null, 'geidea_gateway_settings' );
        add_settings_field( 'GPG_live_public_key', __( 'Live Merchant Public Key', 'geidea-payment-gateway' ), array( $this, 'render_text' ), 'geidea_gateway_settings', 'GPG_live_section', array( 'id' => 'GPG_live_public_key' ) );
        add_settings_field( 'GPG_live_api_password', __( 'Live API Password', 'geidea-payment-gateway' ), array( $this, 'render_password' ), 'geidea_gateway_settings', 'GPG_live_section', array( 'id' => 'GPG_live_api_password' ) );
        add_settings_field( 'GPG_live_api_base_url', __( 'Live API Base URL', 'geidea-payment-gateway' ), array( $this, 'render_text' ), 'geidea_gateway_settings', 'GPG_live_section', array( 'id' => 'GPG_live_api_base_url', 'default' => 'https://api.ksamerchant.geidea.net/' ) );
        add_settings_field( 'GPG_live_checkout_base_url', __( 'Live Hosted Checkout Base URL', 'geidea-payment-gateway' ), array( $this, 'render_text' ), 'geidea_gateway_settings', 'GPG_live_section', array( 'id' => 'GPG_live_checkout_base_url', 'default' => 'https://www.ksamerchant.geidea.net/hpp/checkout/?' ) );

        // Page Redirect Settings
        add_settings_section( 'GPG_redirect_section', __( 'Page Redirect Settings', 'geidea-payment-gateway' ), null, 'geidea_gateway_settings' );
        add_settings_field( 'GPG_success_url', __( 'Success Page URL', 'geidea-payment-gateway' ), array( $this, 'render_url' ), 'geidea_gateway_settings', 'GPG_redirect_section', array( 'id' => 'GPG_success_url' ) );
        add_settings_field( 'GPG_failed_url', __( 'Failed Page URL', 'geidea-payment-gateway' ), array( $this, 'render_url' ), 'geidea_gateway_settings', 'GPG_redirect_section', array( 'id' => 'GPG_failed_url' ) );
        add_settings_field( 'GPG_cancel_url', __( 'Cancel Page URL', 'geidea-payment-gateway' ), array( $this, 'render_url' ), 'geidea_gateway_settings', 'GPG_redirect_section', array( 'id' => 'GPG_cancel_url' ) );
        add_settings_field( 'GPG_pending_url', __( 'Pending / Verification Page URL', 'geidea-payment-gateway' ), array( $this, 'render_url' ), 'geidea_gateway_settings', 'GPG_redirect_section', array( 'id' => 'GPG_pending_url' ) );
    }

    public function sanitize_settings( $input ) {
        $sanitized = array();
        $sanitized['GPG_enabled'] = isset( $input['GPG_enabled'] ) ? 1 : 0;
        $sanitized['GPG_mode'] = isset( $input['GPG_mode'] ) && $input['GPG_mode'] === 'live' ? 'live' : 'sandbox';
        
        $sanitized['GPG_currency'] = sanitize_text_field( $input['GPG_currency'] ?? 'SAR' );
        $sanitized['GPG_language'] = sanitize_text_field( $input['GPG_language'] ?? 'ar' );
        $sanitized['GPG_region'] = 'KSA';
        $sanitized['GPG_phone_code'] = '+966';
        $sanitized['GPG_customer_country'] = 'SAU';

        $sanitized['GPG_debug_log'] = isset( $input['GPG_debug_log'] ) ? 1 : 0;
        $sanitized['GPG_github_token'] = sanitize_text_field( $input['GPG_github_token'] ?? '' );

        $sanitized['GPG_sandbox_public_key'] = sanitize_text_field( $input['GPG_sandbox_public_key'] ?? '' );
        $sanitized['GPG_sandbox_api_password'] = sanitize_text_field( $input['GPG_sandbox_api_password'] ?? '' );
        $sanitized['GPG_sandbox_api_base_url'] = esc_url_raw( $input['GPG_sandbox_api_base_url'] ?? 'https://api.ksamerchant.geidea.net/' );
        $sanitized['GPG_sandbox_checkout_base_url'] = esc_url_raw( $input['GPG_sandbox_checkout_base_url'] ?? 'https://www.ksamerchant.geidea.net/hpp/checkout/?' );

        $sanitized['GPG_live_public_key'] = sanitize_text_field( $input['GPG_live_public_key'] ?? '' );
        $sanitized['GPG_live_api_password'] = sanitize_text_field( $input['GPG_live_api_password'] ?? '' );
        $sanitized['GPG_live_api_base_url'] = esc_url_raw( $input['GPG_live_api_base_url'] ?? 'https://api.ksamerchant.geidea.net/' );
        $sanitized['GPG_live_checkout_base_url'] = esc_url_raw( $input['GPG_live_checkout_base_url'] ?? 'https://www.ksamerchant.geidea.net/hpp/checkout/?' );

        $sanitized['GPG_success_url'] = esc_url_raw( $input['GPG_success_url'] ?? '' );
        $sanitized['GPG_failed_url'] = esc_url_raw( $input['GPG_failed_url'] ?? '' );
        $sanitized['GPG_cancel_url'] = esc_url_raw( $input['GPG_cancel_url'] ?? '' );
        $sanitized['GPG_pending_url'] = esc_url_raw( $input['GPG_pending_url'] ?? '' );

        return $sanitized;
    }

    public function render_checkbox( $args ) {
        $options = get_option( 'GPG_settings' );
        $id = $args['id'];
        $checked = isset( $options[$id] ) && $options[$id] == 1 ? 'checked' : '';
        echo "<input type='checkbox' name='GPG_settings[{$id}]' value='1' {$checked} />";
    }

    public function render_text( $args ) {
        $options = get_option( 'GPG_settings' );
        $id = $args['id'];
        $default = $args['default'] ?? '';
        $value = isset( $options[$id] ) ? esc_attr( $options[$id] ) : $default;
        echo "<input type='text' name='GPG_settings[{$id}]' value='{$value}' class='regular-text' />";
    }

    public function render_readonly_text( $args ) {
        $id = $args['id'];
        $default = $args['default'] ?? '';
        echo "<input type='text' value='{$default}' class='regular-text' readonly disabled />";
        echo "<input type='hidden' name='GPG_settings[{$id}]' value='{$default}' />";
    }

    public function render_password( $args ) {
        $options = get_option( 'GPG_settings' );
        $id = $args['id'];
        $value = esc_attr( $options[$id] ?? '' );
        echo "<input type='password' name='GPG_settings[{$id}]' value='{$value}' class='regular-text' />";
    }

    public function render_url( $args ) {
        $options = get_option( 'GPG_settings' );
        $id = $args['id'];
        $value = esc_url( $options[$id] ?? '' );
        echo "<input type='url' name='GPG_settings[{$id}]' value='{$value}' class='regular-text' />";
    }

    public function render_select( $args ) {
        $options_value = get_option( 'GPG_settings' );
        $id = $args['id'];
        $value = $options_value[$id] ?? 'sandbox';
        echo "<select name='GPG_settings[{$id}]'>";
        foreach ( $args['options'] as $key => $label ) {
            $selected = selected( $value, $key, false );
            echo "<option value='" . esc_attr( $key ) . "' {$selected}>" . esc_html( $label ) . "</option>";
        }
        echo "</select>";
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Geidea Gateway', 'geidea-payment-gateway' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'GPG_settings_group' );
                do_settings_sections( 'geidea_gateway_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function show_admin_notices() {
        $mode = self::get_setting( 'GPG_mode', 'sandbox' );
        if ( $mode === 'live' ) {
            if ( ! is_ssl() ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Geidea Gateway: Live Mode is enabled but the site is not using HTTPS. Secure connection is required.', 'geidea-payment-gateway' ) . '</p></div>';
            }
            if ( empty( self::get_setting( 'GPG_live_public_key' ) ) || empty( self::get_setting( 'GPG_live_api_password' ) ) || empty( self::get_setting( 'GPG_live_checkout_base_url' ) ) ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Geidea Gateway: Live credentials or Checkout URL are missing!', 'geidea-payment-gateway' ) . '</p></div>';
            }
        }
    }

    public static function get_setting( $key, $default = false ) {
        $settings = get_option( 'GPG_settings' );
        return isset( $settings[$key] ) ? $settings[$key] : $default;
    }
}


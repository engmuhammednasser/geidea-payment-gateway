<?php
/**
 * Plugin Name: Geidea Payment Gateway
 * Plugin URI: https://engmuhammednasser.github.io
 * Description: Secure WordPress payment gateway plugin for integrating Geidea HPP Checkout V2 with Saudi car booking websites using Redirect Mode.
 * Version: 2.1.8
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: Muhammed nasser
 * Author URI: https://engmuhammednasser.github.io
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: geidea-payment-gateway
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'GPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GPG_VERSION', '1.1.0' );

// Include necessary files
require_once GPG_PLUGIN_DIR . 'includes/class-gpg-logger.php';
require_once GPG_PLUGIN_DIR . 'includes/class-gpg-settings.php';
require_once GPG_PLUGIN_DIR . 'includes/class-gpg-signature.php';
require_once GPG_PLUGIN_DIR . 'includes/class-gpg-api-client.php';
require_once GPG_PLUGIN_DIR . 'includes/class-gpg-booking.php';
require_once GPG_PLUGIN_DIR . 'includes/class-gpg-rest.php';
require_once GPG_PLUGIN_DIR . 'includes/class-gpg-security.php';

// Initialize the plugin
function GPG_saudi_init_plugin() {
    new GPG_Settings();
    new GPG_Security();
    new GPG_REST();
    new GPG_Booking();
}
add_action( 'plugins_loaded', 'GPG_saudi_init_plugin' );

// GitHub Auto Updater
require_once GPG_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';

// Guard against duplicate slug registration (causes Fatal Error on AJAX/admin requests)
if ( ! has_filter( 'puc_is_slug_in_use-geidea-payment-gateway' ) ) {
    $gpgUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/engmuhammednasser/geidea-payment-gateway/',
        __FILE__,
        'geidea-payment-gateway'
    );
    // Set the branch that contains the stable release.
    $gpgUpdateChecker->setBranch('main');
}

// Add Settings Link to Plugins Page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'gpg_plugin_action_links' );
function gpg_plugin_action_links( $links ) {
    $settings_link = '<a href="admin.php?page=geidea_gateway_settings">' . __( 'Settings', 'geidea-payment-gateway' ) . '</a>';
    $docs_link = '<a href="https://github.com/engmuhammednasser/geidea-payment-gateway" target="_blank">' . __( 'Docs', 'geidea-payment-gateway' ) . '</a>';
    array_unshift( $links, $settings_link, $docs_link );
    return $links;
}

// Activation Onboarding Redirect
register_activation_hook( __FILE__, 'gpg_plugin_activate' );
function gpg_plugin_activate() {
    add_option( 'gpg_do_activation_redirect', true );
}

add_action( 'admin_init', 'gpg_plugin_redirect' );
function gpg_plugin_redirect() {
    if ( get_option( 'gpg_do_activation_redirect', false ) ) {
        delete_option( 'gpg_do_activation_redirect' );
        if ( ! isset( $_GET['activate-multi'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=geidea_gateway_settings' ) );
            exit;
        }
    }
}

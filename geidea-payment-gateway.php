<?php
/**
 * Plugin Name: Geidea Payment Gateway
 * Plugin URI: https://engmuhammednasser.github.io
 * Description: Secure WordPress payment gateway plugin for integrating Geidea HPP Checkout V2 with Saudi car booking websites using Redirect Mode.
 * Version: 2.1.5
 * Author: Muhammed nasser
 * Text Domain: geidea-payment-gateway
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
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$gpgUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/engmuhammednasser/geidea-payment-gateway/',
    __FILE__,
    'geidea-payment-gateway'
);
// Set the branch that contains the stable release.
$gpgUpdateChecker->setBranch('main');


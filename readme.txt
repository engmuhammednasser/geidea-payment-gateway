=== Geidea Payment Gateway ===
Contributors: engmuhammednasser
Tags: payment, geidea, saudi arabia, gateway, car rental
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 2.1.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure WordPress payment gateway plugin for integrating Geidea HPP Checkout V2 with Saudi car booking websites using Redirect Mode.

== Description ==

The **Geidea Payment Gateway** is a standalone, lightweight, and professional WordPress plugin designed specifically to integrate Geidea HPP Checkout V2 into Saudi businesses. 

While optimized for car rental and booking websites, it provides a generic hook `ashhalan_process_payment_redirect` that any theme or plugin can use to seamlessly process payments via Geidea.

### Features
* **Redirect Mode:** Safely redirects users to Geidea's hosted payment page.
* **Sandbox & Live Modes:** Easily switch between test and production environments.
* **Automated Returns:** Automatically handles payment callbacks and redirects users to Success, Failed, or Cancel pages.
* **Secure Architecture:** Built using WordPress standards, ensuring no sensitive data is leaked.
* **Hook-Based Integration:** Completely decoupled from the active theme. Hook into it using `apply_filters`.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/geidea-payment-gateway` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. You will be redirected to the **Geidea Gateway** settings page.
4. Enter your Geidea Merchant Public Key and API Password.
5. Select your environment mode (Sandbox or Live).
6. Save the settings.

== Frequently Asked Questions ==

= Do I need an SSL certificate? =
Yes, Geidea requires your website to have a valid SSL certificate (HTTPS) for Live mode to work securely.

= Where do I find my API credentials? =
You can find your Merchant Public Key and API Password in your Geidea Merchant Portal under API Settings.

= How do I integrate this into my custom theme? =
You can use the built-in WordPress filter to process payments. Example:
`$response = apply_filters( 'ashhalan_process_payment_redirect', false, 'geidea', $order_id, $data, $customer, $qty, $price, $item_id );`

== Screenshots ==

1. screenshot-1.jpg - Geidea Gateway General Settings
2. screenshot-2.jpg - Geidea Gateway Sandbox Settings
3. screenshot-3.png - Geidea Gateway Live Settings
4. screenshot-4.jpg - Geidea Test Tools

== Changelog ==

= 2.1.5 =
* Tweak: Automated the Return URL mechanism to use the home URL.
* Tweak: Removed the confusing "Pending Page URL" setting for better UX.

= 2.1.4 =
* Fix: Restored the global redirect handler to properly process the Geidea payment response (`responseCode`) and route users.

= 2.1.3 =
* Fix: Corrected namespace and class name issues (`GBG_` to `GPG_`).
* Refactor: Completely decoupled the plugin from the theme logic using hooks.

= 1.1.0 =
* Initial release of the standalone plugin.

== Upgrade Notice ==

= 2.1.5 =
This update removes the "Pending Page" setting and automates the return process. No further action is required after updating.

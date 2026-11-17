<?php
/**
 * Plugin Name:       MG Membership
 * Plugin URI:        https://example.com/mg-membership
 * Description:       Complete membership platform: paid memberships, recurring subscriptions, content protection rules, drip content, coupons, Stripe/PayPal/offline payments, member accounts, course management (LMS), seminars & events, certificates, reports and page builder support (Gutenberg, Elementor, Divi, WPBakery, Beaver Builder).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            MG
 * License:           GPL-2.0-or-later
 * Text Domain:       mg-membership
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'MGM_VERSION', '1.0.0' );
define( 'MGM_DB_VERSION', '1.0.0' );
define( 'MGM_FILE', __FILE__ );
define( 'MGM_PATH', plugin_dir_path( __FILE__ ) );
define( 'MGM_URL', plugin_dir_url( __FILE__ ) );
define( 'MGM_BASENAME', plugin_basename( __FILE__ ) );

require_once MGM_PATH . 'includes/class-mgm-plugin.php';

register_activation_hook( __FILE__, array( 'MGM_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MGM_Install', 'deactivate' ) );

/**
 * Main accessor.
 *
 * @return MGM_Plugin
 */
function mgm() {
	return MGM_Plugin::instance();
}

mgm();

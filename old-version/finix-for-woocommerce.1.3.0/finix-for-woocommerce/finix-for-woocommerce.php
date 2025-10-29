<?php
/*
 * Plugin Name:          Finix for WooCommerce
 * Plugin URI:           https://docs.finix.com/additional-resources/plugins/woocommerce-plugin
 * Description:          Take credit card, bank, Apple Pay, and Google Pay payments on your store using Finix.
 * Version:              1.3.0
 * Author:               Finix
 * Author URI:           https://finix.com
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least:    6.7
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Text Domain:          finix-for-woocommerce
 * Domain Path:          /assets/languages
 *
 * WC requires at least: 9.9
 * WC tested up to:      10.2
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WPForms.Comments.PHPDocDefine.MissPHPDoc
const FINIXWC_VERSION        = '1.3.0';
const FINIXWC_PLUGIN_FILE    = __FILE__;
const FINIXWC_MIN_WC_VERSION = '9.9.0';

define( 'FINIXWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FINIXWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// phpcs:enable WPForms.Comments.PHPDocDefine.MissPHPDoc

/**
 * One and the only instance of the plugin.
 */
function finixwc(): FinixWC\Plugin {

	require_once __DIR__ . '/vendor/autoload.php';

	return FinixWC\Plugin::instance();
}

/**
 * WooCommerce is active and all plugins have been loaded.
 * WordPress has not yet initialized the current user data.
 */
function finixwc_load() {

	// Only continue if we have access to our minimum supported version.
	if ( version_compare( wc()->version, FINIXWC_MIN_WC_VERSION, '<' ) ) {
		return;
	}

	finixwc()->init();
	finixwc()->hooks();
}

add_action( 'woocommerce_loaded', 'finixwc_load' );

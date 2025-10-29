<?php
/**
 * Plugin Name: Finix WooCommerce Subscriptions
 * Plugin URI: https://yoursite.com
 * Description: Custom payment gateway integrating Finix payment processing with WooCommerce Subscriptions for Canadian customers
 * Version: 1.6.2
 * Author: Your Name
 * Author URI: https://yoursite.com
 * Text Domain: finix-wc-subs
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * 
 * Changelog:
 * Version 1.6.2 - CRITICAL FIX: Added WooCommerce Blocks integration, Finix.js tokenization, Application ID field, and changed gateway ID to finix_subscriptions
 * Version 1.3.4 - Fixed "Invalid country" error by converting 2-letter ISO country codes to 3-letter ISO codes for Finix API
 * Version 1.3.3 - Updated webhook handling for transfer.updated and subscription.updated events
 * Version 1.3.2 - Fixed webhook authentication to use Basic Auth instead of HMAC
 * Version 1.3.1 - Fixed WooCommerce Subscriptions dependency check
 * Version 1.0.0 - Initial release
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('FINIX_WC_SUBS_VERSION', '1.6.2');
define('FINIX_WC_SUBS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FINIX_WC_SUBS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FINIX_WC_SUBS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Initialize the gateway
 */
add_action('plugins_loaded', 'finix_wc_subs_init_gateway', 11);

function finix_wc_subs_init_gateway() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'finix_wc_subs_woocommerce_missing_notice');
        return;
    }

    // Check if WooCommerce Subscriptions is active
    if (!class_exists('WC_Subscriptions')) {
        add_action('admin_notices', 'finix_wc_subs_subscriptions_missing_notice');
        return;
    }

    // Check if WooCommerce Payment Gateway class exists
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Include required files
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-api.php';
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-gateway.php';
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-webhook-handler.php';
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-customer-portal.php';

    // Include blocks integration if WooCommerce Blocks is active
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-blocks-integration.php';
        add_action('woocommerce_blocks_payment_method_type_registration', 'finix_wc_subs_register_blocks_support');
    }

    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'finix_wc_subs_add_gateway');
}

/**
 * Register blocks support
 */
function finix_wc_subs_register_blocks_support($payment_method_registry) {
    if (class_exists('Finix_Blocks_Integration')) {
        $payment_method_registry->register(new Finix_Blocks_Integration());
    }
}

function finix_wc_subs_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('Finix WooCommerce Subscriptions requires WooCommerce to be installed and active.', 'finix-wc-subs'); ?></p>
    </div>
    <?php
}

function finix_wc_subs_subscriptions_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('Finix WooCommerce Subscriptions requires WooCommerce Subscriptions to be installed and active.', 'finix-wc-subs'); ?></p>
    </div>
    <?php
}

function finix_wc_subs_add_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Finix_Subscriptions';
    return $gateways;
}

/**
 * Add settings link to plugin page
 */
add_filter('plugin_action_links_' . FINIX_WC_SUBS_PLUGIN_BASENAME, 'finix_wc_subs_plugin_action_links');

function finix_wc_subs_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=finix_subscriptions') . '">' . __('Settings', 'finix-wc-subs') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Register customer portal endpoint
 */
add_action('init', 'finix_wc_subs_add_endpoints');

function finix_wc_subs_add_endpoints() {
    add_rewrite_endpoint('finix-subscriptions', EP_ROOT | EP_PAGES);
}

// Flush rewrite rules on activation
register_activation_hook(__FILE__, 'finix_wc_subs_activation');

function finix_wc_subs_activation() {
    finix_wc_subs_add_endpoints();
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'finix_wc_subs_deactivation');

function finix_wc_subs_deactivation() {
    flush_rewrite_rules();
}

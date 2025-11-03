<?php
/**
 * Plugin Name: Finix WooCommerce Subscriptions
 * Plugin URI: https://3riversbiotech.com
 * Description: Custom payment gateway integrating Finix payment processing with WooCommerce Subscriptions for Canadian customers
 * Version: 1.8.2
 * Author: KevinM
 * Author URI: https://3riversbiotech.com
 * Text Domain: finix-wc-subs
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 *
 * Changelog:
 * Version 1.8.2 - CRITICAL FIX: Fixed payment data structure to match working v1.3.0 plugin (removed gateway prefix from fraud_session_id)
 * Version 1.8.1 - TESTING ENHANCEMENT: Added custom logger with JavaScript console capture to bypass wp-debug memory issues
 * Version 1.8.0 - MAJOR REFACTOR: Two-gateway architecture (card/bank) matching official Finix plugin pattern, fixed blocks checkout, subscription support
 * Version 1.7.2 - CRITICAL FIX: Fixed payment data field name mismatch causing 400 error in blocks checkout
 * Version 1.7.1 - Rebuilt JavaScript to match official Finix plugin approach using Finix.CardTokenForm and Finix.BankTokenForm
 * Version 1.7.0 - PRODUCTION READY: Backend buyer creation, payment state handling, tags system, fraud session ID, enhanced error handling
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
define('FINIX_WC_SUBS_VERSION', '1.8.2');
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
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-logger.php';
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-logger-endpoint.php';
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-tags.php';
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-api.php';

    // v1.8.0: Include new gateway classes (card and bank)
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-gateway-base.php';
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-card-gateway.php';
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-bank-gateway.php';

    // Include legacy gateway for backward compatibility (inactive by default)
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-gateway.php';

    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-webhook-handler.php';
    require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-customer-portal.php';

    // Include blocks integration if WooCommerce Blocks is active
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        // v1.8.0: Register separate blocks integrations for card and bank
        require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-card-blocks.php';
        require_once FINIX_WC_SUBS_PLUGIN_DIR . 'includes/class-finix-bank-blocks.php';

        add_action('woocommerce_blocks_payment_method_type_registration', 'finix_wc_subs_register_blocks_support');
    }

    // Add the gateways to WooCommerce
    add_filter('woocommerce_payment_gateways', 'finix_wc_subs_add_gateway');

    // Initialize logger endpoint
    Finix_Logger_Endpoint::init();

    // Enqueue console logger on checkout and cart pages
    add_action('wp_enqueue_scripts', 'finix_wc_subs_enqueue_console_logger');
}

/**
 * Register blocks support - v1.8.0
 * Updated to register separate card and bank gateways
 */
function finix_wc_subs_register_blocks_support($payment_method_registry) {
    // v1.8.0: Register card gateway
    if (class_exists('Finix_Card_Blocks_Integration')) {
        $payment_method_registry->register(new Finix_Card_Blocks_Integration());
    }

    // v1.8.0: Register bank gateway
    if (class_exists('Finix_Bank_Blocks_Integration')) {
        $payment_method_registry->register(new Finix_Bank_Blocks_Integration());
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
    // v1.8.0: Register new card and bank gateways
    $gateways[] = 'WC_Gateway_Finix_Card';
    $gateways[] = 'WC_Gateway_Finix_Bank';

    // Keep legacy gateway for backward compatibility (inactive by default)
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

/**
 * Enqueue console logger script on checkout pages
 */
function finix_wc_subs_enqueue_console_logger() {
    // Only load on checkout, cart, and order-pay pages
    if (!is_checkout() && !is_cart() && !is_wc_endpoint_url('order-pay')) {
        return;
    }

    // Check if logging is enabled
    $card_settings = get_option('woocommerce_finix_gateway_settings', array());
    $bank_settings = get_option('woocommerce_finix_bank_gateway_settings', array());

    $debug_enabled = (
        (!empty($card_settings['debug']) && $card_settings['debug'] === 'yes') ||
        (!empty($bank_settings['debug']) && $bank_settings['debug'] === 'yes')
    );

    if (!$debug_enabled) {
        return;
    }

    wp_enqueue_script(
        'finix-console-logger',
        FINIX_WC_SUBS_PLUGIN_URL . 'assets/js/finix-console-logger.js',
        array('jquery'),
        FINIX_WC_SUBS_VERSION,
        true
    );

    wp_localize_script('finix-console-logger', 'finixConsoleLogger', array(
        'enabled' => '1',
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('finix_logging'),
    ));
}

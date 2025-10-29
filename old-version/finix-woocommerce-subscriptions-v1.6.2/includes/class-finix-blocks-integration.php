<?php
/**
 * Finix Blocks Integration
 * Registers the payment method with WooCommerce Blocks
 * 
 * Version: 1.6.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if the required class exists before using it
if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
    return;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Finix Blocks Integration class
 */
final class Finix_Blocks_Integration extends AbstractPaymentMethodType {

    /**
     * Payment method name
     */
    protected $name = 'finix_subscriptions';

    /**
     * Gateway instance
     */
    private $gateway;

    /**
     * Initialize
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_finix_subscriptions_settings', array());
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset($gateways['finix_subscriptions']) ? $gateways['finix_subscriptions'] : null;
    }

    /**
     * Check if payment method is active
     */
    public function is_active() {
        return $this->gateway && 'yes' === $this->gateway->enabled;
    }

    /**
     * Get payment method script handles
     */
    public function get_payment_method_script_handles() {
        // Enqueue Finix.js library
        $application_id = $this->gateway ? $this->gateway->get_application_id() : '';
        if (empty($application_id)) {
            error_log('Finix Subscriptions: Application ID is missing!');
            return array();
        }

        // Register Finix.js
        wp_register_script(
            'finix-js',
            'https://cdn.finixpayments.com/v1/finix.js',
            array(),
            null,
            true
        );

        // Register our blocks script
        $script_path = '/assets/js/finix-blocks.js';
        $script_url = FINIX_WC_SUBS_PLUGIN_URL . 'assets/js/finix-blocks.js';
        $script_asset_path = FINIX_WC_SUBS_PLUGIN_DIR . 'assets/js/finix-blocks.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array(
                'dependencies' => array('wp-element', 'wp-i18n', 'wc-blocks-registry', 'wp-html-entities'),
                'version' => FINIX_WC_SUBS_VERSION
            );

        wp_register_script(
            'finix-subscriptions-blocks',
            $script_url,
            array_merge($script_asset['dependencies'], array('finix-js')),
            $script_asset['version'],
            true
        );

        // Add inline script to initialize Finix with Application ID
        wp_add_inline_script(
            'finix-subscriptions-blocks',
            'window.finixApplicationId = "' . esc_js($application_id) . '";',
            'before'
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('finix-subscriptions-blocks', 'finix-wc-subs');
        }

        return array('finix-js', 'finix-subscriptions-blocks');
    }

    /**
     * Get payment method data to pass to JS
     */
    public function get_payment_method_data() {
        if (!$this->gateway) {
            return array();
        }

        $is_subscription = false;
        if (class_exists('WC_Subscriptions_Cart')) {
            $is_subscription = WC_Subscriptions_Cart::cart_contains_subscription();
        }

        return array(
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'supports' => $this->get_supported_features(),
            'applicationId' => $this->gateway->get_application_id(),
            'environment' => $this->gateway->testmode ? 'sandbox' : 'live',
            'isSubscription' => $is_subscription,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('finix_payment_nonce')
        );
    }

    /**
     * Get supported features
     */
    public function get_supported_features() {
        return $this->gateway ? $this->gateway->supports : array();
    }
}

<?php
/**
 * Finix Card Gateway - WooCommerce Blocks Integration
 * Version: 1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Finix_Card_Blocks_Integration extends AbstractPaymentMethodType {

    /**
     * Gateway instance
     */
    private $gateway;

    /**
     * Payment method name
     */
    protected $name = 'finix_gateway';

    /**
     * Initialize the payment method
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_finix_gateway_settings', []);
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset($gateways['finix_gateway']) ? $gateways['finix_gateway'] : null;
    }

    /**
     * Check if payment method is active
     */
    public function is_active() {
        return $this->gateway && $this->gateway->is_available();
    }

    /**
     * Get payment method script handles
     */
    public function get_payment_method_script_handles() {
        // Enqueue Finix.js library
        wp_register_script(
            'finix-js-card',
            'https://js.finix.com/v/1/3/2/finix.js',
            [],
            null,
            true
        );

        // Enqueue blocks integration script
        $script_url = FINIX_WC_SUBS_PLUGIN_URL . 'assets/js/finix-card-blocks.js';
        $script_asset_path = FINIX_WC_SUBS_PLUGIN_DIR . 'assets/js/finix-card-blocks.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require($script_asset_path)
            : ['dependencies' => [], 'version' => FINIX_WC_SUBS_VERSION];

        wp_register_script(
            'finix-card-blocks',
            $script_url,
            array_merge($script_asset['dependencies'], ['finix-js-card']),
            $script_asset['version'],
            true
        );

        // Set script translations
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('finix-card-blocks', 'finix-wc-subs');
        }

        return ['finix-js-card', 'finix-card-blocks'];
    }

    /**
     * Get payment method data for the frontend
     */
    public function get_payment_method_data() {
        // Check if cart contains subscription
        $is_subscription = false;
        if (function_exists('wcs_cart_contains_subscription') && wcs_cart_contains_subscription()) {
            $is_subscription = true;
        }

        return [
            'title' => $this->gateway ? $this->gateway->get_option('title') : __('Credit/Debit Card', 'finix-wc-subs'),
            'description' => $this->gateway ? $this->gateway->get_option('description') : '',
            'supports' => $this->get_supported_features(),
            'gateway_id' => 'finix_gateway',
            'application' => $this->gateway ? $this->gateway->get_application_id() : '',
            'merchant' => $this->gateway ? $this->gateway->merchant_id : '',
            'environment' => $this->gateway && $this->gateway->testmode ? 'sandbox' : 'live',
            'nonce' => wp_create_nonce('finix_payment_nonce'),
            'isSubscription' => $is_subscription,
            'text' => [
                'full_name' => __('Full Name', 'finix-wc-subs'),
                'card_number' => __('Card Number', 'finix-wc-subs'),
                'expiry_date' => __('Expiry Date', 'finix-wc-subs'),
                'cvv' => __('CVV', 'finix-wc-subs'),
                'postal_code' => __('Postal Code', 'finix-wc-subs'),
                'receipt_description' => __('Receipt Description (Optional)', 'finix-wc-subs'),
                'receipt_placeholder' => __('e.g., Gym Membership, Monthly Software', 'finix-wc-subs'),
                'receipt_help' => __('This description will appear on your monthly receipts and bank statements.', 'finix-wc-subs')
            ],
            'finix_form_options' => [
                'showAddress' => true,
                'showLabels' => true,
                'labels' => [
                    'name' => __('Full Name', 'finix-wc-subs')
                ],
                'showPlaceholders' => true,
                'placeholders' => [
                    'name' => __('Full Name', 'finix-wc-subs')
                ],
                'hideFields' => ['address_line1', 'address_line2', 'address_city', 'address_state'],
                'requiredFields' => ['name', 'address_country', 'address_postal_code'],
                'hideErrorMessages' => false,
                'errorMessages' => [
                    'name' => __('Please enter a valid name', 'finix-wc-subs'),
                    'address_city' => __('Please enter a valid city', 'finix-wc-subs')
                ]
            ]
        ];
    }

    /**
     * Get supported features
     */
    public function get_supported_features() {
        $features = ['products'];

        if ($this->gateway) {
            if ($this->gateway->supports('subscriptions')) {
                $features[] = 'subscriptions';
            }
            if ($this->gateway->supports('subscription_cancellation')) {
                $features[] = 'subscription_cancellation';
            }
            if ($this->gateway->supports('subscription_suspension')) {
                $features[] = 'subscription_suspension';
            }
            if ($this->gateway->supports('subscription_reactivation')) {
                $features[] = 'subscription_reactivation';
            }
            if ($this->gateway->supports('subscription_amount_changes')) {
                $features[] = 'subscription_amount_changes';
            }
            if ($this->gateway->supports('subscription_date_changes')) {
                $features[] = 'subscription_date_changes';
            }
            if ($this->gateway->supports('refunds')) {
                $features[] = 'refunds';
            }
        }

        return $features;
    }
}

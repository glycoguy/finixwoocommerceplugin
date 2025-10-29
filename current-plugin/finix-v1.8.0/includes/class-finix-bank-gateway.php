<?php
/**
 * Finix Bank Gateway - EFT/ACH Payments
 * Version: 1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Finix_Bank extends WC_Gateway_Finix_Base {

    public function __construct() {
        $this->id = 'finix_bank_gateway';
        $this->icon = '';
        $this->has_fields = false; // Finix.js handles fields
        $this->method_title = __('Finix - Bank Transfer (EFT)', 'finix-wc-subs');
        $this->method_description = __('Accept Canadian bank account payments using Finix with client-side tokenization (PCI compliant). Supports subscriptions.', 'finix-wc-subs');

        // Call parent constructor
        parent::__construct();

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Enqueue payment scripts for classic and blocks checkout
     */
    public function payment_scripts() {
        if (!is_checkout() && !has_block('woocommerce/checkout')) {
            return;
        }

        // Enqueue Finix.js library
        $application_id = $this->get_application_id();
        if (empty($application_id)) {
            return;
        }

        // Finix.js CDN
        wp_enqueue_script(
            'finix-js-bank',
            'https://js.finix.com/v/1/3/2/finix.js',
            array(),
            null,
            true
        );

        // Classic checkout script (for non-blocks checkout)
        if (!has_block('woocommerce/checkout')) {
            wp_enqueue_script(
                'finix-bank-payment',
                FINIX_WC_SUBS_PLUGIN_URL . 'assets/js/finix-bank-payment.js',
                array('jquery', 'finix-js-bank'),
                FINIX_WC_SUBS_VERSION,
                true
            );

            wp_localize_script('finix-bank-payment', 'finixBankSettings', array(
                'application_id' => $application_id,
                'merchant_id' => $this->merchant_id,
                'environment' => $this->testmode ? 'sandbox' : 'live',
                'gateway_id' => $this->id,
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('finix_payment_nonce'),
            ));
        }
    }
}

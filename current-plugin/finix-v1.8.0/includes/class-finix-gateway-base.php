<?php
/**
 * Finix Base Gateway - Shared functionality for Card and Bank gateways
 * Version: 1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class WC_Gateway_Finix_Base extends WC_Payment_Gateway {

    // API credentials
    public $testmode;
    public $application_id;
    public $api_key;
    public $api_secret;
    public $merchant_id;
    public $webhook_username;
    public $webhook_password;

    // Settings
    public $subscriptions_only;

    public function __construct() {
        // Subscription support flags (shared by both gateways)
        $this->supports = array(
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'multiple_subscriptions',
            'refunds',
            'tokenization'
        );

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->subscriptions_only = 'yes' === $this->get_option('subscriptions_only');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->application_id = $this->testmode ? $this->get_option('test_application_id') : $this->get_option('live_application_id');
        $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('live_api_key');
        $this->api_secret = $this->testmode ? $this->get_option('test_api_secret') : $this->get_option('live_api_secret');
        $this->merchant_id = $this->testmode ? $this->get_option('test_merchant_id') : $this->get_option('live_merchant_id');
        $this->webhook_username = $this->testmode ? $this->get_option('test_webhook_username') : $this->get_option('live_webhook_username');
        $this->webhook_password = $this->testmode ? $this->get_option('test_webhook_password') : $this->get_option('live_webhook_password');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Subscription actions
        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
        add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'cancel_subscription'));
        add_action('woocommerce_subscription_status_updated', array($this, 'subscription_status_updated'), 10, 3);

        // Custom field actions
        add_action('woocommerce_checkout_create_order', array($this, 'save_custom_checkout_field'), 10, 2);

        // Blocks checkout support - save payment data to order meta
        add_action('woocommerce_rest_checkout_process_payment_with_context', array($this, 'process_blocks_payment_data'), 10, 2);
    }

    /**
     * Get Application ID
     */
    public function get_application_id() {
        return $this->application_id;
    }

    /**
     * Check if gateway is available
     */
    public function is_available() {
        $is_available = parent::is_available();

        // If "subscriptions only" is enabled, check if cart contains subscriptions
        if ($is_available && $this->subscriptions_only) {
            if (class_exists('WC_Subscriptions_Cart')) {
                $is_available = WC_Subscriptions_Cart::cart_contains_subscription();
            } else {
                $is_available = false;
            }
        }

        return $is_available;
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'finix-wc-subs'),
                'type'    => 'checkbox',
                'label'   => __('Enable this payment gateway', 'finix-wc-subs'),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __('Title', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'finix-wc-subs'),
                'default'     => $this->method_title,
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'finix-wc-subs'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'subscriptions_only' => array(
                'title'       => __('Subscriptions Only', 'finix-wc-subs'),
                'type'        => 'checkbox',
                'label'       => __('Only show this gateway for subscription products', 'finix-wc-subs'),
                'default'     => 'no',
                'description' => __('If enabled, this gateway will only appear when the cart contains subscription products.', 'finix-wc-subs'),
                'desc_tip'    => true,
            ),
            'test_mode_section' => array(
                'title'       => __('Test Mode', 'finix-wc-subs'),
                'type'        => 'title',
                'description' => __('Use test credentials for testing. Switch to live credentials for production.', 'finix-wc-subs'),
            ),
            'testmode' => array(
                'title'   => __('Test Mode', 'finix-wc-subs'),
                'type'    => 'checkbox',
                'label'   => __('Enable Test Mode', 'finix-wc-subs'),
                'default' => 'yes'
            ),
            'test_credentials_section' => array(
                'title'       => __('Test (Sandbox) Credentials', 'finix-wc-subs'),
                'type'        => 'title',
                'description' => __('Obtain your test credentials from the Finix Dashboard (Sandbox environment).', 'finix-wc-subs'),
            ),
            'test_application_id' => array(
                'title'       => __('Test Application ID', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Get this from your Finix sandbox dashboard.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_api_key' => array(
                'title'       => __('Test API Key', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Your Finix sandbox API key.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_api_secret' => array(
                'title'       => __('Test API Secret', 'finix-wc-subs'),
                'type'        => 'password',
                'description' => __('Your Finix sandbox API secret.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_merchant_id' => array(
                'title'       => __('Test Merchant ID', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Your Finix sandbox merchant ID.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_webhook_username' => array(
                'title'       => __('Test Webhook Username', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Username for webhook authentication (test environment).', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_webhook_password' => array(
                'title'       => __('Test Webhook Password', 'finix-wc-subs'),
                'type'        => 'password',
                'description' => __('Password for webhook authentication (test environment).', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'live_credentials_section' => array(
                'title'       => __('Live (Production) Credentials', 'finix-wc-subs'),
                'type'        => 'title',
                'description' => __('Obtain your live credentials from the Finix Dashboard (Production environment).', 'finix-wc-subs'),
            ),
            'live_application_id' => array(
                'title'       => __('Live Application ID', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Get this from your Finix production dashboard.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'live_api_key' => array(
                'title'       => __('Live API Key', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Your Finix production API key.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'live_api_secret' => array(
                'title'       => __('Live API Secret', 'finix-wc-subs'),
                'type'        => 'password',
                'description' => __('Your Finix production API secret.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'live_merchant_id' => array(
                'title'       => __('Live Merchant ID', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Your Finix production merchant ID.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'live_webhook_username' => array(
                'title'       => __('Live Webhook Username', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Username for webhook authentication (production environment).', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'live_webhook_password' => array(
                'title'       => __('Live Webhook Password', 'finix-wc-subs'),
                'type'        => 'password',
                'description' => __('Password for webhook authentication (production environment).', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'webhook_section' => array(
                'title'       => __('Webhook Configuration', 'finix-wc-subs'),
                'type'        => 'title',
                'description' => sprintf(
                    __('Configure separate webhooks for test and live environments. Your webhook URL is: %s', 'finix-wc-subs'),
                    '<strong>' . home_url('/wc-api/finix_subscriptions_webhook/') . '</strong>'
                ),
            ),
        );
    }

    /**
     * Process Payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Payment error: Invalid order.', 'finix-wc-subs'), 'error');
            return array('result' => 'fail');
        }

        try {
            // Get API instance
            $api = new Finix_API(
                $this->api_key,
                $this->api_secret,
                $this->merchant_id,
                $this->testmode
            );

            // Get token from POST data (supports both classic checkout and blocks checkout)
            $gateway_id = $this->id;

            // Try to get from direct POST first (classic checkout)
            $instrument_id = isset($_POST[$gateway_id . '_token']) ? sanitize_text_field($_POST[$gateway_id . '_token']) : '';

            // If not found, try from order meta (blocks checkout stores it there)
            if (empty($instrument_id)) {
                $instrument_id = $order->get_meta('_' . $gateway_id . '_token', true);
            }

            if (empty($instrument_id)) {
                throw new Exception(__('Payment token is missing. Please try again.', 'finix-wc-subs'));
            }

            // Save payment instrument ID for subscriptions
            $order->update_meta_data('_finix_instrument_id', $instrument_id);
            $order->update_meta_data('_finix_gateway_id', $gateway_id);

            // Save fraud session ID if present
            $fraud_session_id = '';
            if (isset($_POST[$gateway_id . '_fraud_session_id'])) {
                $fraud_session_id = sanitize_text_field($_POST[$gateway_id . '_fraud_session_id']);
            } elseif ($order->get_meta('_' . $gateway_id . '_fraud_session_id', true)) {
                $fraud_session_id = $order->get_meta('_' . $gateway_id . '_fraud_session_id', true);
            }

            if (!empty($fraud_session_id)) {
                $order->update_meta_data('_finix_fraud_session_id', $fraud_session_id);
            }

            // Save custom description if present
            $custom_description = '';
            if (isset($_POST['finix_custom_description'])) {
                $custom_description = sanitize_text_field($_POST['finix_custom_description']);
            } elseif ($order->get_meta('_finix_custom_description_temp', true)) {
                $custom_description = $order->get_meta('_finix_custom_description_temp', true);
            }

            if (!empty($custom_description)) {
                $order->update_meta_data('_finix_custom_description', $custom_description);
            }

            // Check if this is a subscription order
            $is_subscription_order = false;
            if (function_exists('wcs_order_contains_subscription')) {
                $is_subscription_order = wcs_order_contains_subscription($order);
            }

            if ($is_subscription_order) {
                // Process subscription
                if (function_exists('wcs_get_subscriptions_for_order')) {
                    $subscriptions = wcs_get_subscriptions_for_order($order);

                    foreach ($subscriptions as $subscription) {
                        $this->create_finix_subscription($subscription, $api, '', $instrument_id, $order);
                    }
                }

                // Process initial payment if needed
                if ($order->get_total() > 0) {
                    $this->process_initial_payment($order, $api, $instrument_id);
                }
            } else {
                // Regular one-time payment
                $this->process_regular_payment($order, $api, $instrument_id);
            }

            $order->save();

            // Return success
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );

        } catch (Exception $e) {
            wc_add_notice(__('Payment error: ', 'finix-wc-subs') . $e->getMessage(), 'error');
            return array(
                'result' => 'fail'
            );
        }
    }

    /**
     * Create Finix subscription
     */
    private function create_finix_subscription($subscription, $api, $identity_id, $instrument_id, $order) {
        $custom_description = $order->get_meta('_finix_custom_description');

        // Map WooCommerce billing period to Finix
        $billing_period = $subscription->get_billing_period();
        $billing_interval_map = array(
            'day'   => 'DAILY',
            'week'  => 'WEEKLY',
            'month' => 'MONTHLY',
            'year'  => 'ANNUALLY'
        );
        $finix_interval = isset($billing_interval_map[$billing_period])
            ? $billing_interval_map[$billing_period]
            : 'MONTHLY';

        $subscription_data = array(
            'amount' => intval($subscription->get_total() * 100),
            'currency' => $order->get_currency(),
            'nickname' => sprintf('Subscription #%s', $subscription->get_id()),
            'fee_amount_data' => array(
                'fixed' => 0
            ),
            'interval_type' => $finix_interval,
            'interval_count' => 1,
            'merchant' => $this->merchant_id,
            'payment_instrument' => $instrument_id,
            'state' => 'ACTIVE',
            'tags' => array(
                'subscription_id' => (string) $subscription->get_id(),
                'order_id' => (string) $order->get_id()
            )
        );

        // Add custom description if present
        if (!empty($custom_description)) {
            $subscription_data['nickname'] = $custom_description;
        }

        $finix_subscription = $api->create_subscription($subscription_data);

        if (!$finix_subscription || !isset($finix_subscription['id'])) {
            throw new Exception(__('Failed to create Finix subscription.', 'finix-wc-subs'));
        }

        // Store Finix subscription ID in WooCommerce subscription
        $subscription->update_meta_data('_finix_subscription_id', $finix_subscription['id']);
        $subscription->update_meta_data('_finix_instrument_id', $instrument_id);
        $subscription->update_meta_data('_finix_gateway_id', $this->id);
        $subscription->save();

        // Store custom description if provided
        if (!empty($custom_description)) {
            $subscription->update_meta_data('_finix_custom_description', $custom_description);
            $subscription->save();
        }

        $subscription->add_order_note(sprintf(
            __('Finix Subscription created successfully. ID: %s', 'finix-wc-subs'),
            $finix_subscription['id']
        ));
    }

    /**
     * Process initial subscription payment
     */
    private function process_initial_payment($order, $api, $instrument_id) {
        $transfer_data = array(
            'amount' => intval($order->get_total() * 100),
            'currency' => $order->get_currency(),
            'merchant' => $this->merchant_id,
            'payment_instrument' => $instrument_id,
            'tags' => array(
                'order_id' => (string) $order->get_id()
            ),
            'fraud_session_id' => $order->get_meta('_finix_fraud_session_id')
        );

        $transfer = $api->create_transfer($transfer_data);

        if (!$transfer || !isset($transfer['id'])) {
            throw new Exception(__('Failed to process initial payment.', 'finix-wc-subs'));
        }

        $order->add_order_note(sprintf(
            __('Finix initial payment processed. Transfer ID: %s', 'finix-wc-subs'),
            $transfer['id']
        ));

        // Payment will be completed via webhook or when we check transfer status
        if (isset($transfer['state']) && $transfer['state'] === 'SUCCEEDED') {
            $order->payment_complete($transfer['id']);
        }
    }

    /**
     * Process regular (non-subscription) payment
     */
    private function process_regular_payment($order, $api, $instrument_id) {
        $transfer_data = array(
            'amount' => intval($order->get_total() * 100),
            'currency' => $order->get_currency(),
            'merchant' => $this->merchant_id,
            'payment_instrument' => $instrument_id,
            'tags' => array(
                'order_id' => (string) $order->get_id()
            ),
            'fraud_session_id' => $order->get_meta('_finix_fraud_session_id')
        );

        $transfer = $api->create_transfer($transfer_data);

        if (!$transfer || !isset($transfer['id'])) {
            throw new Exception(__('Payment failed. Please try again.', 'finix-wc-subs'));
        }

        $order->add_order_note(sprintf(
            __('Finix payment processed. Transfer ID: %s', 'finix-wc-subs'),
            $transfer['id']
        ));

        // Payment will be completed via webhook or when we check transfer status
        if (isset($transfer['state']) && $transfer['state'] === 'SUCCEEDED') {
            $order->payment_complete($transfer['id']);
        }
    }

    /**
     * Process scheduled subscription payment (renewals)
     */
    public function scheduled_subscription_payment($amount_to_charge, $renewal_order) {
        try {
            // Get parent subscription
            $subscriptions = wcs_get_subscriptions_for_renewal_order($renewal_order);

            if (empty($subscriptions)) {
                throw new Exception('No subscription found for renewal order');
            }

            $subscription = reset($subscriptions);

            // Get saved payment instrument ID from subscription
            $instrument_id = $subscription->get_meta('_finix_instrument_id');

            if (empty($instrument_id)) {
                throw new Exception('No payment instrument found for subscription');
            }

            // Get API instance
            $api = new Finix_API(
                $this->api_key,
                $this->api_secret,
                $this->merchant_id,
                $this->testmode
            );

            // Process renewal payment
            $transfer_data = array(
                'amount' => intval($amount_to_charge * 100),
                'currency' => $renewal_order->get_currency(),
                'merchant' => $this->merchant_id,
                'payment_instrument' => $instrument_id,
                'tags' => array(
                    'order_id' => (string) $renewal_order->get_id(),
                    'subscription_id' => (string) $subscription->get_id(),
                    'renewal' => 'true'
                ),
                'fraud_session_id' => $renewal_order->get_meta('_finix_fraud_session_id')
            );

            $transfer = $api->create_transfer($transfer_data);

            if (!$transfer || !isset($transfer['id'])) {
                throw new Exception('Failed to process renewal payment');
            }

            $renewal_order->add_order_note(sprintf(
                __('Finix renewal payment processed. Transfer ID: %s', 'finix-wc-subs'),
                $transfer['id']
            ));

            // Complete payment if succeeded
            if (isset($transfer['state']) && $transfer['state'] === 'SUCCEEDED') {
                $renewal_order->payment_complete($transfer['id']);
            }

        } catch (Exception $e) {
            $renewal_order->update_status('failed', sprintf(
                __('Renewal payment failed: %s', 'finix-wc-subs'),
                $e->getMessage()
            ));
        }
    }

    /**
     * Cancel subscription at Finix
     */
    public function cancel_subscription($subscription) {
        $finix_subscription_id = $subscription->get_meta('_finix_subscription_id');

        if (empty($finix_subscription_id)) {
            return;
        }

        try {
            $api = new Finix_API(
                $this->api_key,
                $this->api_secret,
                $this->merchant_id,
                $this->testmode
            );

            $api->cancel_subscription($finix_subscription_id);

            $subscription->add_order_note(__('Finix subscription cancelled successfully.', 'finix-wc-subs'));

        } catch (Exception $e) {
            $subscription->add_order_note(sprintf(
                __('Failed to cancel Finix subscription: %s', 'finix-wc-subs'),
                $e->getMessage()
            ));
        }
    }

    /**
     * Handle subscription status updates
     */
    public function subscription_status_updated($subscription, $new_status, $old_status) {
        // Only handle if this is our gateway
        if ($subscription->get_payment_method() !== $this->id) {
            return;
        }

        // Handle suspended/on-hold subscriptions
        if ($new_status === 'on-hold' && $old_status === 'active') {
            // Could pause subscription at Finix if supported
        }

        // Handle reactivated subscriptions
        if ($new_status === 'active' && $old_status === 'on-hold') {
            // Could resume subscription at Finix if supported
        }
    }

    /**
     * Save custom checkout field
     */
    public function save_custom_checkout_field($order, $data) {
        if (isset($_POST['finix_custom_description'])) {
            $description = sanitize_text_field($_POST['finix_custom_description']);
            $order->update_meta_data('_finix_custom_description', $description);
        }
    }

    /**
     * Process blocks checkout payment data
     * Saves payment data from WooCommerce Blocks to order meta
     */
    public function process_blocks_payment_data($context, $result) {
        if (!isset($context->payment_data)) {
            return;
        }

        $payment_data = $context->payment_data;
        $order = $context->order;

        // Only process if this is our gateway
        if ($context->payment_method !== $this->id) {
            return;
        }

        // Save token to order meta
        if (isset($payment_data[$this->id . '_token'])) {
            $order->update_meta_data('_' . $this->id . '_token', sanitize_text_field($payment_data[$this->id . '_token']));
        }

        // Save fraud session ID
        if (isset($payment_data[$this->id . '_fraud_session_id'])) {
            $order->update_meta_data('_' . $this->id . '_fraud_session_id', sanitize_text_field($payment_data[$this->id . '_fraud_session_id']));
        }

        // Save custom description
        if (isset($payment_data['finix_custom_description'])) {
            $order->update_meta_data('_finix_custom_description_temp', sanitize_text_field($payment_data['finix_custom_description']));
        }

        $order->save();
    }

    /**
     * Process refund
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('error', __('Order not found.', 'finix-wc-subs'));
        }

        // Get transfer ID from order
        $transfer_id = $order->get_transaction_id();

        if (empty($transfer_id)) {
            return new WP_Error('error', __('Transfer ID not found. Cannot process refund.', 'finix-wc-subs'));
        }

        try {
            $api = new Finix_API(
                $this->api_key,
                $this->api_secret,
                $this->merchant_id,
                $this->testmode
            );

            $refund_data = array(
                'transfer' => $transfer_id,
                'amount' => intval($amount * 100),
                'tags' => array(
                    'order_id' => (string) $order_id,
                    'reason' => $reason
                )
            );

            $refund = $api->create_refund($refund_data);

            if (!$refund || !isset($refund['id'])) {
                return new WP_Error('error', __('Refund failed at Finix.', 'finix-wc-subs'));
            }

            $order->add_order_note(sprintf(
                __('Finix refund processed. Refund ID: %s. Amount: %s', 'finix-wc-subs'),
                $refund['id'],
                wc_price($amount)
            ));

            return true;

        } catch (Exception $e) {
            return new WP_Error('error', $e->getMessage());
        }
    }

    /**
     * Abstract method - child classes must implement payment_scripts()
     */
    abstract public function payment_scripts();
}

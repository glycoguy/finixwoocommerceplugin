<?php
/**
 * Finix Payment Gateway for WooCommerce Subscriptions
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Finix_Subscriptions extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'finix_subscriptions';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = __('Finix Payment Gateway (Subscriptions)', 'finix-wc-subs');
        $this->method_description = __('Accept subscription payments using Finix with client-side tokenization (PCI compliant). Supports credit cards and Canadian bank accounts.', 'finix-wc-subs');

        // Subscription support flags
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
        $this->webhook_secret = $this->get_option('webhook_secret');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_finix_subscriptions_webhook', array($this, 'handle_webhook'));
        
        // Subscription actions
        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
        add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'cancel_subscription'));
        add_action('woocommerce_subscription_status_updated', array($this, 'subscription_status_updated'), 10, 3);

        // Custom field actions
        add_action('woocommerce_checkout_create_order', array($this, 'save_custom_checkout_field'), 10, 2);
        
        // AJAX actions for Finix.js tokenization
        add_action('wp_ajax_finix_associate_token', array($this, 'ajax_associate_token'));
        add_action('wp_ajax_nopriv_finix_associate_token', array($this, 'ajax_associate_token'));
        add_action('wp_ajax_finix_create_bank_instrument', array($this, 'ajax_create_bank_instrument'));
        add_action('wp_ajax_nopriv_finix_create_bank_instrument', array($this, 'ajax_create_bank_instrument'));
        
        // Legacy AJAX action (for classic checkout)
        add_action('wp_ajax_finix_create_payment_token', array($this, 'ajax_create_payment_token'));
        add_action('wp_ajax_nopriv_finix_create_payment_token', array($this, 'ajax_create_payment_token'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        
        // Add custom description field to blocks checkout
        add_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', array($this, 'enqueue_blocks_scripts'));
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
                // If subscriptions plugin not active, don't show gateway
                $is_available = false;
            }
        }
        
        return $is_available;
    }
    
    /**
     * Enqueue payment scripts
     */
    public function payment_scripts() {
        if (!is_checkout() && !is_add_payment_method_page()) {
            return;
        }

        // Enqueue Finix.js for client-side tokenization
        wp_enqueue_script(
            'finix-js',
            'https://cdn.finixpayments.com/v1/finix.js',
            array(),
            null,
            true
        );

        wp_enqueue_style(
            'finix-payment-styles',
            FINIX_WC_SUBS_PLUGIN_URL . 'assets/css/finix-payment.css',
            array(),
            FINIX_WC_SUBS_VERSION
        );

        wp_enqueue_script(
            'finix-payment-script',
            FINIX_WC_SUBS_PLUGIN_URL . 'assets/js/finix-payment.js',
            array('jquery', 'wc-checkout', 'finix-js'),
            FINIX_WC_SUBS_VERSION,
            true
        );

        wp_localize_script('finix-payment-script', 'finix_params', array(
            'nonce' => wp_create_nonce('finix_payment_nonce'),
            'is_subscription' => class_exists('WC_Subscriptions_Cart') && WC_Subscriptions_Cart::cart_contains_subscription(),
            'application_id' => $this->get_application_id(),
            'environment' => $this->testmode ? 'sandbox' : 'live',
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }
    
    /**
     * Enqueue scripts for blocks checkout
     */
    public function enqueue_blocks_scripts() {
        if (!is_checkout()) {
            return;
        }
        
        // Add inline script to show custom description field on blocks checkout
        $inline_script = "
        (function($) {
            $(document).ready(function() {
                // Check if this is a subscription checkout
                var isSubscription = " . (class_exists('WC_Subscriptions_Cart') && WC_Subscriptions_Cart::cart_contains_subscription() ? 'true' : 'false') . ";
                
                if (isSubscription) {
                    // Look for Finix payment method
                    $(document).on('change', 'input[name=\"radio-control-wc-payment-method-options\"]', function() {
                        if ($(this).val() === 'finix') {
                            // Add custom description field if it doesn't exist
                            if ($('#finix-custom-description').length === 0) {
                                var descField = '<div class=\"wc-block-components-text-input wc-block-components-finix-custom-description\">' +
                                    '<label for=\"finix-custom-description\">" . esc_js(__('Receipt Description', 'finix-wc-subs')) . "</label>' +
                                    '<input type=\"text\" id=\"finix-custom-description\" name=\"finix_custom_description\" ' +
                                    'placeholder=\"" . esc_js(__('Optional: Add a note that will appear on your monthly receipts', 'finix-wc-subs')) . "\" />' +
                                    '<small>" . esc_js(__('This description will appear on your monthly subscription receipts.', 'finix-wc-subs')) . "</small>' +
                                    '</div>';
                                $('.wc-block-checkout__payment-method--finix').append(descField);
                            }
                        }
                    });
                }
            });
        })(jQuery);
        ";
        
        wp_add_inline_script('wc-checkout', $inline_script);
    }
    
    /**
     * AJAX handler for creating payment token
     */
    public function ajax_create_payment_token() {
        check_ajax_referer('finix_payment_nonce', 'nonce');

        if (!isset($_POST['card_data'])) {
            wp_send_json_error(array('message' => 'Missing card data'));
        }

        $card_data = $_POST['card_data'];

        try {
            $api = new Finix_API(
                $this->api_key,
                $this->api_secret,
                $this->merchant_id,
                $this->testmode
            );

            // Prepare card data with proper year format
            $exp_year = $card_data['exp_year'];
            if (strlen($exp_year) == 2) {
                $exp_year = '20' . $exp_year;
            }

            // First create identity
            $identity_data = array(
                'first_name' => sanitize_text_field($card_data['first_name']),
                'last_name' => sanitize_text_field($card_data['last_name']),
                'email' => sanitize_email($card_data['email']),
                'phone' => sanitize_text_field($card_data['phone']),
                'address_line1' => sanitize_text_field($card_data['address_line1']),
                'address_line2' => sanitize_text_field($card_data['address_line2']),
                'city' => sanitize_text_field($card_data['city']),
                'state' => sanitize_text_field($card_data['state']),
                'postal_code' => sanitize_text_field($card_data['postal_code']),
                'country' => sanitize_text_field($card_data['country']),
                'user_id' => get_current_user_id()
            );

            $identity = $api->create_identity($identity_data);

            // Create payment instrument
            $payment_instrument_data = array(
                'name' => sanitize_text_field($card_data['name']),
                'number' => sanitize_text_field($card_data['number']),
                'exp_month' => str_pad(sanitize_text_field($card_data['exp_month']), 2, '0', STR_PAD_LEFT),
                'exp_year' => $exp_year,
                'cvv' => sanitize_text_field($card_data['cvv']),
                'address_line1' => sanitize_text_field($card_data['address_line1']),
                'address_line2' => sanitize_text_field($card_data['address_line2']),
                'city' => sanitize_text_field($card_data['city']),
                'state' => sanitize_text_field($card_data['state']),
                'postal_code' => sanitize_text_field($card_data['postal_code']),
                'country' => sanitize_text_field($card_data['country'])
            );

            $instrument = $api->create_payment_instrument($identity['id'], $payment_instrument_data);

            wp_send_json_success(array(
                'identity_id' => $identity['id'],
                'instrument_id' => $instrument['id']
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler to associate Finix.js token with identity
     * Used by blocks checkout after client-side tokenization
     */
    public function ajax_associate_token() {
        check_ajax_referer('finix_payment_nonce', 'nonce');

        if (!isset($_POST['token']) || !isset($_POST['billing_data'])) {
            wp_send_json_error(array('message' => 'Missing required data'));
        }

        $token = sanitize_text_field($_POST['token']);
        $billing_data = $_POST['billing_data'];

        try {
            $api = new Finix_API(
                $this->api_key,
                $this->api_secret,
                $this->merchant_id,
                $this->testmode
            );

            // Create identity
            $identity_data = array(
                'first_name' => sanitize_text_field($billing_data['first_name']),
                'last_name' => sanitize_text_field($billing_data['last_name']),
                'email' => sanitize_email($billing_data['email']),
                'phone' => sanitize_text_field($billing_data['phone']),
                'address_line1' => sanitize_text_field($billing_data['address_line1']),
                'address_line2' => sanitize_text_field($billing_data['address_line2']),
                'city' => sanitize_text_field($billing_data['city']),
                'state' => sanitize_text_field($billing_data['state']),
                'postal_code' => sanitize_text_field($billing_data['postal_code']),
                'country' => sanitize_text_field($billing_data['country']),
                'user_id' => get_current_user_id()
            );

            $identity = $api->create_identity($identity_data);

            // Associate token with identity
            $instrument = $api->associate_token($token, $identity['id']);

            wp_send_json_success(array(
                'identity_id' => $identity['id'],
                'instrument_id' => $instrument['id']
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler to create Canadian bank account instrument
     * Used by blocks checkout for EFT payments
     */
    public function ajax_create_bank_instrument() {
        check_ajax_referer('finix_payment_nonce', 'nonce');

        if (!isset($_POST['bank_data'])) {
            wp_send_json_error(array('message' => 'Missing bank account data'));
        }

        $bank_data = $_POST['bank_data'];

        try {
            $api = new Finix_API(
                $this->api_key,
                $this->api_secret,
                $this->merchant_id,
                $this->testmode
            );

            // Create identity
            $identity_data = array(
                'first_name' => sanitize_text_field($bank_data['first_name']),
                'last_name' => sanitize_text_field($bank_data['last_name']),
                'email' => sanitize_email($bank_data['email']),
                'phone' => sanitize_text_field($bank_data['phone']),
                'address_line1' => sanitize_text_field($bank_data['address_line1']),
                'address_line2' => sanitize_text_field($bank_data['address_line2']),
                'city' => sanitize_text_field($bank_data['city']),
                'state' => sanitize_text_field($bank_data['state']),
                'postal_code' => sanitize_text_field($bank_data['postal_code']),
                'country' => 'CAN', // Force Canada for bank accounts
                'user_id' => get_current_user_id()
            );

            $identity = $api->create_identity($identity_data);

            // Create bank instrument
            $bank_instrument_data = array(
                'type' => 'BANK_ACCOUNT',
                'identity_id' => $identity['id'],
                'account_number' => sanitize_text_field($bank_data['account_number']),
                'account_type' => sanitize_text_field($bank_data['account_type']),
                'bank_code' => sanitize_text_field($bank_data['institution_number']),
                'branch_code' => sanitize_text_field($bank_data['transit_number']),
                'name' => sanitize_text_field($bank_data['account_holder_name']),
                'country' => 'CAN'
            );

            $instrument = $api->create_bank_instrument($bank_instrument_data);

            wp_send_json_success(array(
                'identity_id' => $identity['id'],
                'instrument_id' => $instrument['id']
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Initialize gateway settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'finix-wc-subs'),
                'type'    => 'checkbox',
                'label'   => __('Enable Finix Payment Gateway', 'finix-wc-subs'),
                'default' => 'no'
            ),
            'subscriptions_only' => array(
                'title'       => __('Subscriptions Only', 'finix-wc-subs'),
                'type'        => 'checkbox',
                'label'       => __('Only show this gateway for subscription products', 'finix-wc-subs'),
                'description' => __('When enabled, this payment method will only appear when the cart contains subscription products.', 'finix-wc-subs'),
                'default'     => 'no',
                'desc_tip'    => true
            ),
            'title' => array(
                'title'       => __('Title', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Payment method title that customers see during checkout', 'finix-wc-subs'),
                'default'     => __('Credit Card (Finix)', 'finix-wc-subs'),
                'desc_tip'    => true
            ),
            'description' => array(
                'title'       => __('Description', 'finix-wc-subs'),
                'type'        => 'textarea',
                'description' => __('Payment method description that customers see during checkout', 'finix-wc-subs'),
                'default'     => __('Pay securely using your credit card.', 'finix-wc-subs'),
                'desc_tip'    => true
            ),
            'testmode' => array(
                'title'       => __('Test mode', 'finix-wc-subs'),
                'label'       => __('Enable Test Mode', 'finix-wc-subs'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in test mode using test API credentials.', 'finix-wc-subs'),
                'default'     => 'yes',
                'desc_tip'    => true
            ),
            'test_application_id' => array(
                'title'       => __('Test Application ID', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Your Finix Application ID for sandbox environment (required for Finix.js tokenization).', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'test_api_key' => array(
                'title'       => __('Test API Key', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Get your API credentials from your Finix Dashboard.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'test_api_secret' => array(
                'title'       => __('Test API Secret', 'finix-wc-subs'),
                'type'        => 'password',
                'description' => __('Get your API credentials from your Finix Dashboard.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'test_merchant_id' => array(
                'title'       => __('Test Merchant ID', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Get your Merchant ID from your Finix Dashboard.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'live_application_id' => array(
                'title'       => __('Live Application ID', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Your Finix Application ID for production environment (required for Finix.js tokenization).', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'live_api_key' => array(
                'title'       => __('Live API Key', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Get your API credentials from your Finix Dashboard.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'live_api_secret' => array(
                'title'       => __('Live API Secret', 'finix-wc-subs'),
                'type'        => 'password',
                'description' => __('Get your API credentials from your Finix Dashboard.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'live_merchant_id' => array(
                'title'       => __('Live Merchant ID', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('Get your Merchant ID from your Finix Dashboard.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'webhook_username' => array(
                'title'       => __('Webhook Username', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('The username from your Finix webhook configuration (Basic Authentication).', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'webhook_password' => array(
                'title'       => __('Webhook Password', 'finix-wc-subs'),
                'type'        => 'password',
                'description' => sprintf(
                    __('The password from your Finix webhook configuration. Configure webhooks in your Finix Dashboard to point to: %s', 'finix-wc-subs'),
                    home_url('/wc-api/finix_webhook/')
                ),
                'default'     => '',
                'desc_tip'    => false
            )
        );
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        // Check if this is a subscription checkout
        $is_subscription = false;
        if (class_exists('WC_Subscriptions_Cart')) {
            $is_subscription = WC_Subscriptions_Cart::cart_contains_subscription();
        }

        ?>
        <fieldset id="finix-payment-form" class="finix-payment-form">
            <div class="form-row form-row-wide">
                <label><?php _e('Card Number', 'finix-wc-subs'); ?> <span class="required">*</span></label>
                <input id="finix-card-number" type="text" autocomplete="off" placeholder="â€¢â€¢â€¢â€¢ â€¢â€¢â€¢â€¢ â€¢â€¢â€¢â€¢ â€¢â€¢â€¢â€¢" />
            </div>
            <div class="form-row form-row-first">
                <label><?php _e('Expiry Date (MM/YY)', 'finix-wc-subs'); ?> <span class="required">*</span></label>
                <input id="finix-card-expiry" type="text" autocomplete="off" placeholder="MM / YY" />
            </div>
            <div class="form-row form-row-last">
                <label><?php _e('Card Code (CVV)', 'finix-wc-subs'); ?> <span class="required">*</span></label>
                <input id="finix-card-cvc" type="text" autocomplete="off" placeholder="CVV" />
            </div>
            <div class="clear"></div>

            <?php if ($is_subscription): ?>
            <div class="form-row form-row-wide">
                <label><?php _e('Receipt Description', 'finix-wc-subs'); ?></label>
                <input id="finix-custom-description" name="finix_custom_description" type="text" 
                       placeholder="<?php _e('Optional: Add a note that will appear on your monthly receipts', 'finix-wc-subs'); ?>" />
                <small><?php _e('This description will appear on your monthly subscription receipts.', 'finix-wc-subs'); ?></small>
            </div>
            <?php endif; ?>

            <input type="hidden" id="finix-payment-token" name="finix_payment_token" />
            <input type="hidden" id="finix-identity-id" name="finix_identity_id" />
        </fieldset>
        <?php
    }

    /**
     * Save custom checkout field
     */
    public function save_custom_checkout_field($order, $data) {
        if (!empty($_POST['finix_custom_description'])) {
            $order->update_meta_data('_finix_custom_description', sanitize_text_field($_POST['finix_custom_description']));
        }
    }

    /**
     * Validate payment fields
     */
    public function validate_fields() {
        if (empty($_POST['finix_payment_token'])) {
            wc_add_notice(__('Payment error: Missing payment information. Please try again.', 'finix-wc-subs'), 'error');
            return false;
        }
        return true;
    }

    /**
     * Process payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        try {
            // Get API instance
            $api = new Finix_API(
                $this->api_key,
                $this->api_secret,
                $this->merchant_id,
                $this->testmode
            );

            $identity_id = sanitize_text_field($_POST['finix_identity_id']);
            $instrument_id = sanitize_text_field($_POST['finix_payment_token']);

            // Save payment instrument for subscriptions
            $order->update_meta_data('_finix_identity_id', $identity_id);
            $order->update_meta_data('_finix_instrument_id', $instrument_id);

            // Check if this is a subscription order
            if (wcs_order_contains_subscription($order)) {
                // Process subscription
                $subscriptions = wcs_get_subscriptions_for_order($order);
                
                foreach ($subscriptions as $subscription) {
                    $this->create_finix_subscription($subscription, $api, $identity_id, $instrument_id, $order);
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
            'amount' => intval($subscription->get_total() * 100), // Convert to cents
            'currency' => $order->get_currency(),
            'nickname' => sprintf('Subscription #%s', $subscription->get_id()),
            'billing_interval' => $finix_interval,
            'identity_id' => $identity_id,
            'instrument_id' => $instrument_id,
            'order_id' => $order->get_id(),
            'wc_subscription_id' => $subscription->get_id(),
            'custom_description' => $custom_description
        );

        // Add trial period if exists
        if ($subscription->get_trial_end_date()) {
            $trial_days = floor(($subscription->get_trial_end_date()->getTimestamp() - time()) / DAY_IN_SECONDS);
            if ($trial_days > 0) {
                $subscription_data['trial_days'] = $trial_days;
            }
        }

        // Create subscription in Finix
        $finix_subscription = $api->create_subscription($subscription_data);

        // Save Finix subscription ID
        $subscription->update_meta_data('_finix_subscription_id', $finix_subscription['id']);
        $subscription->update_meta_data('_finix_identity_id', $identity_id);
        $subscription->update_meta_data('_finix_instrument_id', $instrument_id);
        $subscription->save();

        // Add order note
        $order->add_order_note(sprintf(
            __('Finix subscription created: %s', 'finix-wc-subs'),
            $finix_subscription['id']
        ));
    }

    /**
     * Process initial payment
     */
    private function process_initial_payment($order, $api, $instrument_id) {
        $transfer_data = array(
            'amount' => intval($order->get_total() * 100),
            'currency' => $order->get_currency(),
            'instrument_id' => $instrument_id,
            'order_id' => $order->get_id()
        );

        $transfer = $api->create_transfer($transfer_data);
        
        $order->update_meta_data('_finix_transfer_id', $transfer['id']);
        $order->payment_complete($transfer['id']);
        $order->add_order_note(sprintf(
            __('Finix payment completed. Transfer ID: %s', 'finix-wc-subs'),
            $transfer['id']
        ));
    }

    /**
     * Process regular payment
     */
    private function process_regular_payment($order, $api, $instrument_id) {
        $this->process_initial_payment($order, $api, $instrument_id);
    }

    /**
     * Process scheduled subscription payment
     */
    public function scheduled_subscription_payment($amount, $renewal_order) {
        try {
            $subscription = wcs_get_subscription($renewal_order->get_meta('_subscription_renewal'));
            
            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            $api = new Finix_API(
                $this->api_key,
                $this->api_secret,
                $this->merchant_id,
                $this->testmode
            );

            $instrument_id = $subscription->get_meta('_finix_instrument_id');
            
            if (!$instrument_id) {
                throw new Exception('Payment instrument not found');
            }

            $this->process_initial_payment($renewal_order, $api, $instrument_id);

        } catch (Exception $e) {
            $renewal_order->update_status('failed', sprintf(
                __('Finix renewal payment failed: %s', 'finix-wc-subs'),
                $e->getMessage()
            ));
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel_subscription($subscription) {
        $finix_subscription_id = $subscription->get_meta('_finix_subscription_id');
        
        if (!$finix_subscription_id) {
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
        // Handle status changes that need to sync with Finix
        // This can be extended based on requirements
    }

    /**
     * Process refund
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        $transfer_id = $order->get_meta('_finix_transfer_id');

        if (!$transfer_id) {
            return new WP_Error('error', __('Refund failed: No Finix transfer ID found.', 'finix-wc-subs'));
        }

        try {
            $api = new Finix_API(
                $this->api_key,
                $this->api_secret,
                $this->merchant_id,
                $this->testmode
            );

            $refund_amount = $amount ? intval($amount * 100) : null;
            $api->refund_transfer($transfer_id, $refund_amount);

            $order->add_order_note(sprintf(
                __('Finix refund completed. Amount: %s', 'finix-wc-subs'),
                wc_price($amount)
            ));

            return true;

        } catch (Exception $e) {
            return new WP_Error('error', $e->getMessage());
        }
    }
}

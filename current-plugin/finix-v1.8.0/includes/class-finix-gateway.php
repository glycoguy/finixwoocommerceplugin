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
        $this->webhook_username = $this->testmode ? $this->get_option('test_webhook_username') : $this->get_option('live_webhook_username');
        $this->webhook_password = $this->testmode ? $this->get_option('test_webhook_password') : $this->get_option('live_webhook_password');

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
        // Official Finix.js CDN URL from Finix official plugin
        wp_enqueue_script(
            'finix-js',
            'https://js.finix.com/v/1/3/2/finix.js',
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

        // Finix form options matching official plugin
        $finix_form_options = array(
            'showAddress' => true,
            'showLabels' => true,
            'labels' => array(
                'name' => __('Full Name', 'finix-wc-subs')
            ),
            'showPlaceholders' => true,
            'placeholders' => array(
                'name' => __('Full Name', 'finix-wc-subs')
            ),
            'hideFields' => array('address_line1', 'address_line2', 'address_city', 'address_state'),
            'requiredFields' => array('name', 'address_country', 'address_postal_code'),
            'hideErrorMessages' => false,
            'errorMessages' => array(
                'name' => __('Please enter a valid full name', 'finix-wc-subs'),
                'address_city' => __('Please enter a valid city', 'finix-wc-subs')
            )
        );

        wp_localize_script('finix-payment-script', 'finix_params', array(
            'nonce' => wp_create_nonce('finix_payment_nonce'),
            'is_subscription' => class_exists('WC_Subscriptions_Cart') && WC_Subscriptions_Cart::cart_contains_subscription(),
            'application' => $this->get_application_id(),
            'merchant' => $this->merchant_id,
            'environment' => $this->testmode ? 'sandbox' : 'live',
            'ajax_url' => admin_url('admin-ajax.php'),
            'gateway_id' => $this->id,
            'finix_form_options' => $finix_form_options,
            'finixForms' => array(), // Will hold form instances
            'text' => array(
                'full_name' => __('Full Name', 'finix-wc-subs'),
                'error_messages' => array(
                    'name' => __('Please enter a valid full name', 'finix-wc-subs'),
                    'address_city' => __('Please enter a valid city', 'finix-wc-subs')
                )
            )
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
                        if ($(this).val() === 'finix_subscriptions') {
                            // Add custom description field if it doesn't exist
                            if ($('#finix-custom-description').length === 0) {
                                var descField = '<div class=\"wc-block-components-text-input wc-block-components-finix-custom-description\">' +
                                    '<label for=\"finix-custom-description\">" . esc_js(__('Receipt Description', 'finix-wc-subs')) . "</label>' +
                                    '<input type=\"text\" id=\"finix-custom-description\" name=\"finix_custom_description\" ' +
                                    'placeholder=\"" . esc_js(__('Optional: Add a note that will appear on your monthly receipts', 'finix-wc-subs')) . "\" />' +
                                    '<small>" . esc_js(__('This description will appear on your monthly subscription receipts.', 'finix-wc-subs')) . "</small>' +
                                    '</div>';
                                $('.wc-block-checkout__payment-method--finix_subscriptions').append(descField);
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
            'webhook_settings_title' => array(
                'title'       => __('Webhook Configuration', 'finix-wc-subs'),
                'type'        => 'title',
                'description' => sprintf(
                    __('Configure separate webhooks for test and live environments. Your webhook URL is: %s', 'finix-wc-subs'),
                    '<strong>' . home_url('/wc-api/finix_subscriptions_webhook/') . '</strong>'
                )
            ),
            'test_webhook_username' => array(
                'title'       => __('Test Webhook Username', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('The username from your Finix TEST webhook configuration (Basic Authentication).', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'test_webhook_password' => array(
                'title'       => __('Test Webhook Password', 'finix-wc-subs'),
                'type'        => 'password',
                'description' => __('The password from your Finix TEST webhook configuration.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'live_webhook_username' => array(
                'title'       => __('Live Webhook Username', 'finix-wc-subs'),
                'type'        => 'text',
                'description' => __('The username from your Finix LIVE webhook configuration (Basic Authentication).', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            ),
            'live_webhook_password' => array(
                'title'       => __('Live Webhook Password', 'finix-wc-subs'),
                'type'        => 'password',
                'description' => __('The password from your Finix LIVE webhook configuration.', 'finix-wc-subs'),
                'default'     => '',
                'desc_tip'    => true
            )
        );
    }

    /**
     * Payment form on checkout page - v1.7.1
     * Uses Finix.CardTokenForm and Finix.BankTokenForm
     */
    public function payment_fields() {
        // Display description
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        // Check if this is a subscription checkout
        $is_subscription = false;
        if (class_exists('WC_Subscriptions_Cart')) {
            $is_subscription = WC_Subscriptions_Cart::cart_contains_subscription();
        }

        $gateway_id = esc_attr($this->id);
        ?>
        <fieldset id="<?php echo $gateway_id; ?>-payment-form" class="finix-payment-form">

            <!-- Payment Type Selector -->
            <div class="form-row form-row-wide finix-payment-type-selector">
                <label class="finix-payment-type-label"><?php _e('Payment Method', 'finix-wc-subs'); ?></label>
                <div class="finix-payment-type-options" style="margin-top: 10px;">
                    <label class="finix-radio-label" style="margin-right: 20px; display: inline-block;">
                        <input type="radio" name="<?php echo $gateway_id; ?>_payment_type" value="card" checked="checked" style="margin-right: 5px;" />
                        <?php _e('Credit Card', 'finix-wc-subs'); ?>
                    </label>
                    <label class="finix-radio-label" style="display: inline-block;">
                        <input type="radio" name="<?php echo $gateway_id; ?>_payment_type" value="bank" style="margin-right: 5px;" />
                        <?php _e('Bank Account (EFT)', 'finix-wc-subs'); ?>
                    </label>
                </div>
            </div>

            <!-- Card Form Container - Finix.CardTokenForm will render here -->
            <div id="<?php echo $gateway_id; ?>-card-fields" class="finix-payment-fields" style="display: block;">
                <div id="<?php echo $gateway_id; ?>-card-form" class="finix-card-form-container" style="margin-top: 20px;">
                    <!-- Finix.CardTokenForm renders here via JavaScript -->
                </div>
            </div>

            <!-- Bank Form Container - Finix.BankTokenForm will render here -->
            <div id="<?php echo $gateway_id; ?>-bank-fields" class="finix-payment-fields" style="display: none;">
                <div id="<?php echo $gateway_id; ?>-bank-form" class="finix-bank-form-container" style="margin-top: 20px;">
                    <!-- Finix.BankTokenForm renders here via JavaScript -->
                </div>
            </div>

            <!-- Receipt Description (Subscriptions Only) -->
            <?php if ($is_subscription): ?>
            <div class="form-row form-row-wide finix-receipt-description" style="margin-top: 20px;">
                <label for="finix-custom-description"><?php _e('Receipt Description (Optional)', 'finix-wc-subs'); ?></label>
                <input id="finix-custom-description" name="finix_custom_description" type="text"
                       class="input-text"
                       placeholder="<?php _e('e.g., Gym Membership, Monthly Software', 'finix-wc-subs'); ?>"
                       maxlength="50"
                       style="width: 100%; padding: 10px; margin-top: 5px;" />
                <small style="display: block; margin-top: 5px; color: #666;">
                    <?php _e('This description will appear on your monthly receipts and bank statements.', 'finix-wc-subs'); ?>
                </small>
            </div>
            <?php endif; ?>

            <!-- Hidden Fields for Token Data -->
            <input type="hidden" id="<?php echo $gateway_id; ?>_token" name="<?php echo $gateway_id; ?>_token" />
            <input type="hidden" id="<?php echo $gateway_id; ?>_fraud_session_id" name="<?php echo $gateway_id; ?>_fraud_session_id" />
            <input type="hidden" id="<?php echo $gateway_id; ?>_payment_type_value" name="<?php echo $gateway_id; ?>_payment_type_value" />
        </fieldset>
        <?php
    }

    /**
     * Save custom checkout field
     */
    public function save_custom_checkout_field($order, $data) {
        // Only process if this is the selected payment method
        if (!isset($_POST['payment_method']) || $_POST['payment_method'] !== $this->id) {
            return;
        }

        if (!empty($_POST['finix_custom_description'])) {
            $order->update_meta_data('_finix_custom_description', sanitize_text_field($_POST['finix_custom_description']));
        }
    }

    /**
     * Validate payment fields - v1.7.1
     */
    public function validate_fields() {
        $gateway_id = $this->id;

        // Check if token exists
        if (empty($_POST[$gateway_id . '_token'])) {
            wc_add_notice(__('Payment error: Missing payment information. Please try again.', 'finix-wc-subs'), 'error');
            return false;
        }

        return true;
    }

    /**
     * Process payment - v1.7.1
     * Updated with NULL safety checks
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

            // Get token from POST data (v1.7.1 uses gateway_id prefix)
            $gateway_id = $this->id;
            $instrument_id = isset($_POST[$gateway_id . '_token']) ? sanitize_text_field($_POST[$gateway_id . '_token']) : '';

            if (empty($instrument_id)) {
                throw new Exception(__('Payment token is missing. Please try again.', 'finix-wc-subs'));
            }

            // Save payment instrument ID for subscriptions
            $order->update_meta_data('_finix_instrument_id', $instrument_id);

            // Save fraud session ID if present
            if (isset($_POST[$gateway_id . '_fraud_session_id'])) {
                $order->update_meta_data('_finix_fraud_session_id', sanitize_text_field($_POST[$gateway_id . '_fraud_session_id']));
            }

            // Save payment type (card or bank) - v1.7.2 fix
            $payment_type = '';
            if (isset($_POST[$gateway_id . '_payment_type_value'])) {
                $payment_type = sanitize_text_field($_POST[$gateway_id . '_payment_type_value']);
                $order->update_meta_data('_finix_payment_type', $payment_type);
            }

            // Fallback: if no payment type specified, default to 'card'
            if (empty($payment_type)) {
                $payment_type = 'card';
                $order->update_meta_data('_finix_payment_type', $payment_type);
            }

            // Check if this is a subscription order (only if WooCommerce Subscriptions is active)
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

        // Validate API response
        if (empty($finix_subscription) || !is_array($finix_subscription) || !isset($finix_subscription['id'])) {
            throw new Exception(__('Invalid response from Finix API when creating subscription. Please check your API credentials and try again.', 'finix-wc-subs'));
        }

        // Save Finix subscription ID and custom description
        $subscription->update_meta_data('_finix_subscription_id', $finix_subscription['id']);
        $subscription->update_meta_data('_finix_identity_id', $identity_id);
        $subscription->update_meta_data('_finix_instrument_id', $instrument_id);
        $subscription->update_meta_data('_finix_custom_description', $custom_description);
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

        // Get the payment state from the transfer result
        $state = $api->get_payment_state($transfer);

        // Store transfer ID
        $order->update_meta_data('_finix_transfer_id', isset($transfer['id']) ? $transfer['id'] : '');
        $order->save();

        // Handle different payment states
        switch ($state) {
            case 'SUCCEEDED':
                // Card payments - complete immediately
                $order->payment_complete(isset($transfer['id']) ? $transfer['id'] : '');
                $order->add_order_note(sprintf(
                    __('Finix payment completed. Transfer ID: %s, State: SUCCEEDED', 'finix-wc-subs'),
                    isset($transfer['id']) ? $transfer['id'] : 'unknown'
                ));
                break;

            case 'PENDING':
                // ACH/Bank payments - mark as on-hold
                $order->update_status('on-hold', sprintf(
                    __('Finix payment pending (ACH). Transfer ID: %s. Payment will complete when funds clear in 3-5 business days.', 'finix-wc-subs'),
                    isset($transfer['id']) ? $transfer['id'] : 'unknown'
                ));
                break;

            case 'FAILED':
                // Failed payments
                $order->update_status('failed', sprintf(
                    __('Finix payment failed. Transfer ID: %s', 'finix-wc-subs'),
                    isset($transfer['id']) ? $transfer['id'] : 'unknown'
                ));
                throw new Exception(__('Payment failed. Please try again or use a different payment method.', 'finix-wc-subs'));
                break;

            case 'CANCELED':
                // Canceled payments
                $order->update_status('cancelled', sprintf(
                    __('Finix payment canceled. Transfer ID: %s', 'finix-wc-subs'),
                    isset($transfer['id']) ? $transfer['id'] : 'unknown'
                ));
                throw new Exception(__('Payment was canceled.', 'finix-wc-subs'));
                break;

            default:
                // Unknown state - be cautious and mark on-hold
                $order->update_status('on-hold', sprintf(
                    __('Finix payment status unknown. Transfer ID: %s, State: %s. Please check Finix Dashboard.', 'finix-wc-subs'),
                    isset($transfer['id']) ? $transfer['id'] : 'unknown',
                    $state
                ));
                break;
        }
    }

    /**
     * Process regular payment
     */
    private function process_regular_payment($order, $api, $instrument_id) {
        $this->process_initial_payment($order, $api, $instrument_id);
    }

    /**
     * Process scheduled subscription payment - v1.7.1
     * Updated with NULL safety checks
     */
    public function scheduled_subscription_payment($amount, $renewal_order) {
        // Check if WooCommerce Subscriptions functions exist
        if (!function_exists('wcs_get_subscription')) {
            $renewal_order->update_status('failed', __('WooCommerce Subscriptions not active.', 'finix-wc-subs'));
            return;
        }

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

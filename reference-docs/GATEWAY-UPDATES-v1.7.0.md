# GATEWAY CLASS UPDATES FOR v1.7.0

## Critical Changes to includes/class-finix-gateway.php

This document shows the EXACT changes needed for v1.7.0. Apply these changes to the existing gateway class.

---

## Change 1: Update Class Name and Version Comment

**Location:** Lines 10, 16-17

**OLD:**
```php
class WC_Gateway_Finix_Subscriptions extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'finix_subscriptions';
        $this->method_title = __('Finix Payment Gateway (Subscriptions)', 'finix-wc-subs');
        $this->method_description = __('Accept subscription payments using Finix with client-side tokenization (PCI compliant). Supports credit cards and Canadian bank accounts.', 'finix-wc-subs');
```

**NEW:**
```php
/**
 * Finix Payment Gateway for WooCommerce Subscriptions
 * Version: 1.7.0
 * 
 * Critical improvements in this version:
 * - Backend buyer identity creation (don't trust frontend)
 * - Payment state handling (PENDING, SUCCEEDED, FAILED)
 * - Tags system integration for transaction tracking
 * - Enhanced error handling and logging
 * - Fraud session ID support
 */

class WC_Gateway_Finix_Subscriptions extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'finix_subscriptions';
        $this->method_title = __('Finix Payment Gateway (Subscriptions)', 'finix-wc-subs');
        $this->method_description = __('Accept subscription payments using Finix with PCI-compliant tokenization. v1.7.0 adds production-ready features: backend buyer creation, payment state handling, and enhanced transaction tracking.', 'finix-wc-subs');
```

---

## Change 2: Add Fraud Session ID to Localized Script

**Location:** Around line 139-145 in `payment_scripts()` method

**OLD:**
```php
wp_localize_script('finix-payment-script', 'finix_params', array(
    'nonce' => wp_create_nonce('finix_payment_nonce'),
    'is_subscription' => class_exists('WC_Subscriptions_Cart') && WC_Subscriptions_Cart::cart_contains_subscription(),
    'application_id' => $this->get_application_id(),
    'environment' => $this->testmode ? 'sandbox' : 'live',
    'ajax_url' => admin_url('admin-ajax.php')
));
```

**NEW:**
```php
wp_localize_script('finix-payment-script', 'finix_params', array(
    'nonce' => wp_create_nonce('finix_payment_nonce'),
    'is_subscription' => class_exists('WC_Subscriptions_Cart') && WC_Subscriptions_Cart::cart_contains_subscription(),
    'application_id' => $this->get_application_id(),
    'environment' => $this->testmode ? 'sandbox' : 'live',
    'merchant_id' => $this->merchant_id, // NEW: For fraud session ID
    'ajax_url' => admin_url('admin-ajax.php')
));
```

---

## Change 3: Fix AJAX Handlers to Return Structured Responses

**Location:** Replace `ajax_associate_token()` method (around line 261-307)

**REPLACE ENTIRE METHOD WITH:**
```php
/**
 * AJAX handler to associate Finix.js token with identity
 * v1.7.0: Enhanced with better error handling and structured responses
 */
public function ajax_associate_token() {
    check_ajax_referer('finix_payment_nonce', 'nonce');

    if (!isset($_POST['token']) || !isset($_POST['billing_data'])) {
        wp_send_json_error(array('message' => __('Missing required data', 'finix-wc-subs')));
        return;
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

        // Create identity with Tags
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

        // Create tags for buyer
        $tags = new Finix_Tags();
        $tags->add('payment_type', 'card');
        $tags->add('created_via', 'ajax_blocks_checkout');

        $identity_result = $api->create_identity($identity_data, $tags);
        
        // Check if identity creation succeeded
        if ($identity_result['status'] !== 201 || empty($identity_result['id'])) {
            wp_send_json_error(array(
                'message' => __('Failed to create buyer identity. Please try again.', 'finix-wc-subs'),
                'details' => $identity_result['error'] ?? 'Unknown error'
            ));
            return;
        }

        // Associate token with identity
        $instrument_result = $api->associate_token($token, $identity_result['id']);
        
        // Check if token association succeeded
        if (empty($instrument_result['id'])) {
            wp_send_json_error(array(
                'message' => __('Failed to associate payment method. Please try again.', 'finix-wc-subs'),
                'details' => $instrument_result['error'] ?? 'Unknown error'
            ));
            return;
        }

        wp_send_json_success(array(
            'identity_id' => $identity_result['id'],
            'instrument_id' => $instrument_result['id']
        ));

    } catch (Exception $e) {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->error('Token association failed: ' . $e->getMessage(), array(
                'source' => 'finix-subscriptions-ajax'
            ));
        }
        wp_send_json_error(array('message' => __('Payment processing error. Please try again.', 'finix-wc-subs')));
    }
}
```

---

## Change 4: Fix Bank Instrument AJAX Handler

**Location:** Replace `ajax_create_bank_instrument()` method (around line 309-369)

**REPLACE ENTIRE METHOD WITH:**
```php
/**
 * AJAX handler to create Canadian bank account instrument
 * v1.7.0: Enhanced with better error handling and Tags
 */
public function ajax_create_bank_instrument() {
    check_ajax_referer('finix_payment_nonce', 'nonce');

    if (!isset($_POST['bank_data'])) {
        wp_send_json_error(array('message' => __('Missing bank account data', 'finix-wc-subs')));
        return;
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

        // Create tags
        $tags = new Finix_Tags();
        $tags->add('payment_type', 'bank');
        $tags->add('bank_account_type', sanitize_text_field($bank_data['account_type']));
        $tags->add('created_via', 'ajax_blocks_checkout');

        $identity_result = $api->create_identity($identity_data, $tags);
        
        // Check if identity creation succeeded
        if ($identity_result['status'] !== 201 || empty($identity_result['id'])) {
            wp_send_json_error(array(
                'message' => __('Failed to create buyer identity. Please try again.', 'finix-wc-subs'),
                'details' => $identity_result['error'] ?? 'Unknown error'
            ));
            return;
        }

        // Create bank instrument
        $bank_instrument_data = array(
            'identity_id' => $identity_result['id'],
            'account_number' => sanitize_text_field($bank_data['account_number']),
            'account_type' => sanitize_text_field($bank_data['account_type']),
            'bank_code' => sanitize_text_field($bank_data['institution_number']),
            'branch_code' => sanitize_text_field($bank_data['transit_number']),
            'name' => sanitize_text_field($bank_data['account_holder_name']),
            'country' => 'CAN'
        );

        $instrument_result = $api->create_bank_instrument($bank_instrument_data);
        
        // Check if instrument creation succeeded
        if (empty($instrument_result['id'])) {
            wp_send_json_error(array(
                'message' => __('Failed to create bank payment method. Please try again.', 'finix-wc-subs'),
                'details' => $instrument_result['error'] ?? 'Unknown error'
            ));
            return;
        }

        wp_send_json_success(array(
            'identity_id' => $identity_result['id'],
            'instrument_id' => $instrument_result['id']
        ));

    } catch (Exception $e) {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->error('Bank instrument creation failed: ' . $e->getMessage(), array(
                'source' => 'finix-subscriptions-ajax'
            ));
        }
        wp_send_json_error(array('message' => __('Payment processing error. Please try again.', 'finix-wc-subs')));
    }
}
```

---

## Change 5: COMPLETELY REPLACE process_payment() Method

**THIS IS THE MOST CRITICAL CHANGE**

**Location:** Find the `process_payment()` method (look for `public function process_payment($order_id)`)

**REPLACE THE ENTIRE METHOD WITH THIS:**

```php
/**
 * Process payment
 * v1.7.0: CRITICAL - Creates buyer on BACKEND, handles payment states properly
 * 
 * This is a complete rewrite from v1.6.2 that fixes:
 * 1. Backend buyer creation (no longer trusts frontend AJAX)
 * 2. Payment state handling (PENDING, SUCCEEDED, FAILED)
 * 3. Tags integration for tracking
 * 4. Fraud session ID support
 * 5. Enhanced error handling
 */
public function process_payment($order_id) {
    $order = wc_get_order($order_id);
    
    // Get logger
    $logger = function_exists('wc_get_logger') ? wc_get_logger() : null;

    try {
        // Get API instance
        $api = new Finix_API(
            $this->api_key,
            $this->api_secret,
            $this->merchant_id,
            $this->testmode
        );

        // CRITICAL: Create buyer identity on BACKEND
        // Don't trust frontend data - this is the proper way
        $buyer_data = array(
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'address_line1' => $order->get_billing_address_1(),
            'address_line2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'postal_code' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country(),
            'user_id' => $order->get_customer_id()
        );
        
        // Create tags for buyer
        $buyer_tags = Finix_Tags::from_order($order);
        $buyer_tags->add('created_at', gmdate('Y-m-d H:i:s'));
        
        $buyer_result = $api->create_identity($buyer_data, $buyer_tags);
        
        // Check if buyer creation succeeded
        if ($buyer_result['status'] !== 201 || empty($buyer_result['id'])) {
            // Customer-facing message (generic)
            wc_add_notice(__('Unable to process payment. Please try again.', 'finix-wc-subs'), 'error');
            
            // Admin order note (detailed)
            $order->add_order_note('Finix Buyer Creation Failed: ' . ($buyer_result['error'] ?? 'Unknown error'));
            
            // WooCommerce logger (very detailed)
            if ($logger) {
                $logger->error('Buyer creation failed', array(
                    'source' => 'finix-subscriptions',
                    'order_id' => $order->get_id(),
                    'status' => $buyer_result['status'],
                    'error' => $buyer_result['error'] ?? 'Unknown'
                ));
            }
            
            return array('result' => 'failure');
        }
        
        $buyer_id = $buyer_result['id'];
        
        // Get the payment token from frontend
        // THIS is properly validated now since we created the buyer ourselves
        $instrument_id = sanitize_text_field($_POST['finix_payment_token'] ?? '');
        
        if (empty($instrument_id)) {
            wc_add_notice(__('Payment information is missing. Please try again.', 'finix-wc-subs'), 'error');
            $order->add_order_note('Payment failed: Missing instrument ID');
            return array('result' => 'failure');
        }
        
        // Associate the frontend token with our backend-created buyer
        $associate_result = $api->associate_token($instrument_id, $buyer_id);
        
        if (empty($associate_result['id'])) {
            wc_add_notice(__('Unable to process payment method. Please try again.', 'finix-wc-subs'), 'error');
            $order->add_order_note('Payment failed: Token association failed - ' . ($associate_result['error'] ?? 'Unknown'));
            return array('result' => 'failure');
        }
        
        // Use the associated instrument ID
        $instrument_id = $associate_result['id'];

        // Save payment instrument for subscriptions
        $order->update_meta_data('_finix_identity_id', $buyer_id);
        $order->update_meta_data('_finix_instrument_id', $instrument_id);

        // Check if this is a subscription order
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
            // Process subscription
            $subscriptions = wcs_get_subscriptions_for_order($order);
            
            foreach ($subscriptions as $subscription) {
                $this->create_finix_subscription($subscription, $api, $buyer_id, $instrument_id, $order);
            }

            // Process initial payment if needed
            if ($order->get_total() > 0) {
                $this->process_initial_payment($order, $api, $instrument_id);
            } else {
                // No initial payment needed (free trial)
                $order->payment_complete();
                $order->add_order_note(__('Subscription activated with free trial. No initial payment required.', 'finix-wc-subs'));
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
        // Log exception with full trace
        if ($logger) {
            $logger->error('Payment exception: ' . $e->getMessage(), array(
                'source' => 'finix-subscriptions',
                'order_id' => $order->get_id(),
                'trace' => $e->getTraceAsString()
            ));
        }
        
        wc_add_notice(__('Payment error. Please try again.', 'finix-wc-subs'), 'error');
        $order->add_order_note('Payment exception: ' . $e->getMessage());
        
        return array('result' => 'failure');
    }
}
```

---

## Change 6: REPLACE process_initial_payment() Method

**Find and REPLACE the entire `process_initial_payment()` method with:**

```php
/**
 * Process initial payment
 * v1.7.0: Enhanced with payment state handling and tags
 */
private function process_initial_payment($order, $api, $instrument_id) {
    // Get fraud session ID if provided
    $fraud_session_id = !empty($_POST['finix_fraud_session_id']) 
        ? sanitize_text_field(wp_unslash($_POST['finix_fraud_session_id'])) 
        : '';
    
    // Get coupon codes
    $coupon_codes = $order->get_coupon_codes();
    $order_coupons = !empty($coupon_codes) ? implode(',', $coupon_codes) : '';
    
    $transfer_data = array(
        'amount' => intval($order->get_total() * 100),
        'currency' => $order->get_currency(),
        'instrument_id' => $instrument_id,
        'order_id' => $order->get_id(),
        'fraud_session_id' => $fraud_session_id,
        'order_coupons' => $order_coupons
    );

    // Create tags for transfer
    $transfer_tags = Finix_Tags::from_order($order);
    $transfer_tags->add('payment_purpose', 'initial_subscription_payment');
    
    $transfer_result = $api->create_transfer($transfer_data, $transfer_tags);
    
    // Check if transfer was created
    if ($transfer_result['status'] !== 201 || empty($transfer_result['id'])) {
        $order->update_status('failed', 'Payment failed: ' . ($transfer_result['error'] ?? 'Unknown error'));
        throw new Exception('Transfer creation failed: ' . ($transfer_result['error'] ?? 'Unknown'));
    }
    
    // Save transfer ID
    $order->update_meta_data('_finix_transfer_id', $transfer_result['id']);
    
    // CRITICAL: Handle payment state properly
    $state = $api->get_payment_state($transfer_result);
    
    switch ($state) {
        case 'SUCCEEDED':
            // Card payments - immediate success
            $order->payment_complete($transfer_result['id']);
            $order->add_order_note(sprintf(
                __('Finix payment succeeded. Transfer ID: %s', 'finix-wc-subs'),
                $transfer_result['id']
            ));
            wc_reduce_stock_levels($order->get_id());
            break;
            
        case 'PENDING':
        case 'UNKNOWN':
            // ACH/Bank payments - takes time to clear
            $order->update_status('on-hold', sprintf(
                __('Awaiting payment confirmation. Transfer ID: %s', 'finix-wc-subs'),
                $transfer_result['id']
            ));
            wc_add_notice(
                __('Your payment is being processed. You will receive confirmation once it completes.', 'finix-wc-subs'),
                'notice'
            );
            break;
            
        case 'FAILED':
        case 'CANCELED':
            $order->update_status('failed', sprintf(
                __('Payment failed. Transfer ID: %s', 'finix-wc-subs'),
                $transfer_result['id']
            ));
            throw new Exception('Payment failed with state: ' . $state);
            break;
            
        default:
            $order->add_order_note('Unexpected payment state: ' . $state . '. Transfer ID: ' . $transfer_result['id']);
            $order->update_status('on-hold', __('Payment in unexpected state. Please check Finix dashboard.', 'finix-wc-subs'));
    }
}
```

---

## Change 7: Add get_payment_state() Wrapper Method

**ADD this new method after process_initial_payment():**

```php
/**
 * Get human-readable payment state
 * Helper method for logging and display
 */
private function get_payment_state_label($state) {
    $labels = array(
        'SUCCEEDED' => __('Succeeded', 'finix-wc-subs'),
        'PENDING' => __('Pending', 'finix-wc-subs'),
        'FAILED' => __('Failed', 'finix-wc-subs'),
        'CANCELED' => __('Canceled', 'finix-wc-subs'),
        'UNKNOWN' => __('Unknown', 'finix-wc-subs')
    );
    
    return isset($labels[$state]) ? $labels[$state] : $state;
}
```

---

## Summary of Changes

1. ✅ Backend buyer creation (lines in process_payment)
2. ✅ Payment state handling (SUCCEEDED, PENDING, FAILED)
3. ✅ Tags integration throughout
4. ✅ Enhanced error handling
5. ✅ Fraud session ID support
6. ✅ Structured API responses
7. ✅ Better logging

## Files This Affects

- `includes/class-finix-gateway.php` (THIS FILE - apply all changes above)
- `includes/class-finix-api.php` (already updated in v1.7.0)
- `includes/class-finix-tags.php` (new file already created)

## Testing After Changes

1. Test credit card payments - should mark as SUCCEEDED immediately
2. Test bank account payments - should mark as PENDING (on-hold)
3. Check WooCommerce logs for detailed error messages
4. Verify tags appear in Finix Dashboard
5. Test subscription creation with both payment types

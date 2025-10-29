<?php
/**
 * Finix Webhook Handler
 * Processes webhook notifications from Finix
 * Version: 1.8.0 - Updated to support both card and bank gateways
 */

if (!defined('ABSPATH')) {
    exit;
}

class Finix_Webhook_Handler {

    public static function init() {
        add_action('woocommerce_api_finix_subscriptions_webhook', array(__CLASS__, 'handle_webhook'));
    }

    /**
     * Handle incoming webhook - v1.8.0
     * Updated with safe Basic Authentication
     * Supports both card and bank gateways
     */
    public static function handle_webhook() {
        $payload = file_get_contents('php://input');

        self::log('Webhook received');

        // Authenticate webhook request
        if (!self::authenticate_webhook()) {
            self::log('Webhook authentication failed - returning 401');
            status_header(401);
            header('WWW-Authenticate: Basic realm="Finix Webhook"');
            exit;
        }

        self::log('Webhook authenticated successfully');

        // Handle empty payload (Finix webhook validation test)
        if (empty($payload)) {
            self::log('Empty payload - webhook validation test');
            status_header(200);
            exit;
        }

        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            self::log('Invalid webhook payload');
            status_header(200); // Return 200 to prevent retries
            exit;
        }

        self::log('Processing event: ' . $event['type']);

        switch ($event['type']) {
            case 'transfer.created':
            case 'transfer.updated':
                self::handle_transfer_event($event);
                break;

            case 'subscription.created':
            case 'subscription.updated':
                self::handle_subscription_event($event);
                break;

            default:
                self::log('Unhandled event type: ' . $event['type']);
                break;
        }

        status_header(200);
        exit;
    }

    /**
     * Authenticate webhook request using Basic Auth - v1.8.0
     * Safely validates credentials against gateway settings
     * Updated to support both card and bank gateways
     */
    private static function authenticate_webhook() {
        // Get gateway instance
        if (!function_exists('WC') || !WC()->payment_gateways()) {
            self::log('WooCommerce not available for authentication');
            return false;
        }

        $gateways = WC()->payment_gateways->payment_gateways();

        // Try to find any Finix gateway (card, bank, or legacy)
        $gateway = null;
        if (isset($gateways['finix_gateway'])) {
            $gateway = $gateways['finix_gateway'];
        } elseif (isset($gateways['finix_bank_gateway'])) {
            $gateway = $gateways['finix_bank_gateway'];
        } elseif (isset($gateways['finix_subscriptions'])) {
            $gateway = $gateways['finix_subscriptions'];
        }

        if (!$gateway) {
            self::log('No Finix gateway found');
            return false;
        }

        // Get expected credentials based on test mode
        $expected_username = $gateway->webhook_username;
        $expected_password = $gateway->webhook_password;

        // If credentials are not configured, allow webhook (backward compatibility)
        if (empty($expected_username) || empty($expected_password)) {
            self::log('Webhook credentials not configured - allowing request');
            return true;
        }

        // Parse Authorization header safely
        $auth_header = null;

        // Try different methods to get Authorization header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $auth_header = $headers['Authorization'];
            }
        }

        if (empty($auth_header)) {
            self::log('No Authorization header found');
            return false;
        }

        // Parse Basic Auth header
        if (strpos($auth_header, 'Basic ') !== 0) {
            self::log('Authorization header is not Basic Auth');
            return false;
        }

        $encoded_credentials = substr($auth_header, 6);
        $decoded_credentials = base64_decode($encoded_credentials);

        if ($decoded_credentials === false) {
            self::log('Failed to decode Authorization header');
            return false;
        }

        // Split username:password
        $credentials_parts = explode(':', $decoded_credentials, 2);

        if (count($credentials_parts) !== 2) {
            self::log('Invalid Authorization header format');
            return false;
        }

        $provided_username = $credentials_parts[0];
        $provided_password = $credentials_parts[1];

        // Compare credentials (timing-safe comparison)
        $username_match = hash_equals($expected_username, $provided_username);
        $password_match = hash_equals($expected_password, $provided_password);

        if ($username_match && $password_match) {
            self::log('Webhook credentials verified');
            return true;
        }

        self::log('Webhook credentials do not match');
        return false;
    }

    /**
     * Handle transfer events - v1.7.1
     * Updated with NULL safety checks
     */
    private static function handle_transfer_event($event) {
        // Validate event data structure
        if (!isset($event['data']) || !is_array($event['data'])) {
            self::log('Invalid transfer event structure');
            return;
        }

        $transfer_id = isset($event['data']['id']) ? $event['data']['id'] : null;
        $state = isset($event['data']['state']) ? $event['data']['state'] : '';
        $order_id = isset($event['data']['tags']['order_id']) ? $event['data']['tags']['order_id'] : null;

        if (!$transfer_id) {
            self::log('No transfer ID in transfer event');
            return;
        }

        if (!$order_id) {
            self::log('No order ID in transfer event');
            return;
        }

        // Check if WooCommerce functions exist
        if (!function_exists('wc_get_order')) {
            self::log('WooCommerce not active');
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            self::log('Order not found: ' . $order_id);
            return;
        }

        switch ($state) {
            case 'SUCCEEDED':
                if (!$order->is_paid()) {
                    $order->payment_complete($transfer_id);
                    $order->add_order_note(sprintf(
                        __('Finix payment confirmed via webhook. Transfer ID: %s', 'finix-wc-subs'),
                        $transfer_id
                    ));
                    self::log('Payment processed for order: ' . $order_id);
                }
                break;

            case 'FAILED':
                $failure_message = isset($event['data']['failure_message']) ? $event['data']['failure_message'] : 'Unknown error';
                $order->update_status('failed', sprintf(
                    __('Finix payment failed: %s', 'finix-wc-subs'),
                    $failure_message
                ));
                self::log('Payment failed for order: ' . $order_id);
                break;
        }
    }

    /**
     * Handle subscription events - v1.7.1
     * Updated with NULL safety checks
     */
    private static function handle_subscription_event($event) {
        // Validate event data structure
        if (!isset($event['data']) || !is_array($event['data'])) {
            self::log('Invalid subscription event structure');
            return;
        }

        $finix_subscription_id = isset($event['data']['id']) ? $event['data']['id'] : null;
        $state = isset($event['data']['state']) ? $event['data']['state'] : '';
        $wc_subscription_id = isset($event['data']['tags']['subscription_id']) ? $event['data']['tags']['subscription_id'] : null;

        if (!$finix_subscription_id) {
            self::log('No Finix subscription ID in event');
            return;
        }

        // Check if WooCommerce Subscriptions functions exist
        if (!function_exists('wcs_get_subscriptions') || !function_exists('wcs_get_subscription')) {
            self::log('WooCommerce Subscriptions not active');
            return;
        }

        $subscription = null;

        if (!$wc_subscription_id) {
            $subscriptions = wcs_get_subscriptions(array(
                'meta_key' => '_finix_subscription_id',
                'meta_value' => $finix_subscription_id
            ));

            if (empty($subscriptions)) {
                self::log('Subscription not found for Finix ID: ' . $finix_subscription_id);
                return;
            }

            $subscription = reset($subscriptions);
        } else {
            $subscription = wcs_get_subscription($wc_subscription_id);
        }

        if (!$subscription) {
            self::log('Subscription not found');
            return;
        }

        switch ($state) {
            case 'ACTIVE':
                $subscription->add_order_note(sprintf(
                    __('Finix subscription confirmed via webhook. ID: %s', 'finix-wc-subs'),
                    $finix_subscription_id
                ));
                self::log('Subscription confirmed: ' . $subscription->get_id());
                break;

            case 'CANCELED':
            case 'CANCELLED':
                if (!$subscription->has_status('cancelled')) {
                    $subscription->update_status('cancelled', __('Subscription cancelled via Finix webhook.', 'finix-wc-subs'));
                    self::log('Subscription cancelled: ' . $subscription->get_id());
                }
                break;

            case 'PAST_DUE':
                $subscription->update_status('on-hold', __('Subscription payment past due.', 'finix-wc-subs'));
                self::log('Subscription marked as past due: ' . $subscription->get_id());
                break;

            case 'EXPIRED':
                $subscription->update_status('expired', __('Subscription expired via Finix.', 'finix-wc-subs'));
                self::log('Subscription expired: ' . $subscription->get_id());
                break;
        }
    }

    /**
     * Log webhook events
     */
    private static function log($message) {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info($message, array('source' => 'finix-webhooks'));
        }
    }
}

Finix_Webhook_Handler::init();

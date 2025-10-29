<?php
/**
 * Finix Webhook Handler
 * Processes webhook notifications from Finix
 */

if (!defined('ABSPATH')) {
    exit;
}

class Finix_Webhook_Handler {

    public static function init() {
        add_action('woocommerce_api_finix_webhook', array(__CLASS__, 'handle_webhook'));
    }

    /**
     * Handle incoming webhook
     */
    public static function handle_webhook() {
        $payload = file_get_contents('php://input');

        self::log('Webhook received');

        $gateway = WC()->payment_gateways()->payment_gateways()['finix'];
        $webhook_username = $gateway->get_option('webhook_username');
        $webhook_password = $gateway->get_option('webhook_password');

        if ($webhook_username && $webhook_password) {
            if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
                self::log('Missing authentication credentials');
                header('WWW-Authenticate: Basic realm="Finix Webhook"');
                status_header(401);
                exit;
            }

            if ($_SERVER['PHP_AUTH_USER'] !== $webhook_username || $_SERVER['PHP_AUTH_PW'] !== $webhook_password) {
                self::log('Invalid authentication credentials');
                header('WWW-Authenticate: Basic realm="Finix Webhook"');
                status_header(401);
                exit;
            }

            self::log('Webhook authentication successful');
        }

        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            self::log('Invalid webhook payload');
            status_header(400);
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
     * Handle transfer events
     */
    private static function handle_transfer_event($event) {
        $transfer_id = $event['data']['id'];
        $state = isset($event['data']['state']) ? $event['data']['state'] : '';
        $order_id = isset($event['data']['tags']['order_id']) ? $event['data']['tags']['order_id'] : null;

        if (!$order_id) {
            self::log('No order ID in transfer event');
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
     * Handle subscription events
     */
    private static function handle_subscription_event($event) {
        $finix_subscription_id = $event['data']['id'];
        $state = isset($event['data']['state']) ? $event['data']['state'] : '';
        $wc_subscription_id = isset($event['data']['tags']['subscription_id']) ? $event['data']['tags']['subscription_id'] : null;

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

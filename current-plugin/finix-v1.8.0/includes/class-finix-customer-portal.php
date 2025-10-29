<?php
/**
 * Finix Customer Portal
 * Allows customers to manage their subscriptions
 * Version: 1.8.0 - Updated to support both card and bank gateways
 */

if (!defined('ABSPATH')) {
    exit;
}

class Finix_Customer_Portal {

    public static function init() {
        add_filter('woocommerce_account_menu_items', array(__CLASS__, 'add_account_menu_item'));
        add_action('woocommerce_account_finix-subscriptions_endpoint', array(__CLASS__, 'subscription_management_content'));
        add_action('template_redirect', array(__CLASS__, 'handle_subscription_actions'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('wp_ajax_finix_update_description', array(__CLASS__, 'handle_description_update'));
    }

    public static function add_account_menu_item($items) {
        $new_items = array();
        
        foreach ($items as $key => $item) {
            $new_items[$key] = $item;
            
            if ($key === 'subscriptions' || ($key === 'orders' && !isset($items['subscriptions']))) {
                $new_items['finix-subscriptions'] = __('Manage Subscriptions', 'finix-wc-subs');
            }
        }
        
        return $new_items;
    }

    public static function subscription_management_content() {
        $user_id = get_current_user_id();
        $subscriptions = wcs_get_users_subscriptions($user_id);
        
        $finix_subscriptions = array();
        foreach ($subscriptions as $subscription) {
            if ($subscription->get_meta('_finix_subscription_id')) {
                $finix_subscriptions[] = $subscription;
            }
        }
        
        if (empty($finix_subscriptions)) {
            echo '<div class="woocommerce-info">';
            _e('You have no active subscriptions managed through Finix.', 'finix-wc-subs');
            echo '</div>';
            return;
        }
        
        self::display_subscriptions_table($finix_subscriptions);
    }

    private static function display_subscriptions_table($subscriptions) {
        ?>
        <h2><?php _e('Your Subscriptions', 'finix-wc-subs'); ?></h2>
        
        <table class="shop_table shop_table_responsive my_account_subscriptions">
            <thead>
                <tr>
                    <th><?php _e('Status', 'finix-wc-subs'); ?></th>
                    <th><?php _e('Subscription', 'finix-wc-subs'); ?></th>
                    <th><?php _e('Next Payment', 'finix-wc-subs'); ?></th>
                    <th><?php _e('Total', 'finix-wc-subs'); ?></th>
                    <th><?php _e('Custom Description', 'finix-wc-subs'); ?></th>
                    <th><?php _e('Actions', 'finix-wc-subs'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscriptions as $subscription): ?>
                <tr>
                    <td data-title="<?php _e('Status', 'finix-wc-subs'); ?>">
                        <span class="subscription-status status-<?php echo esc_attr($subscription->get_status()); ?>">
                            <?php echo esc_html(wcs_get_subscription_status_name($subscription->get_status())); ?>
                        </span>
                    </td>
                    <td data-title="<?php _e('Subscription', 'finix-wc-subs'); ?>">
                        <a href="<?php echo esc_url($subscription->get_view_order_url()); ?>">
                            #<?php echo esc_html($subscription->get_order_number()); ?>
                        </a>
                    </td>
                    <td data-title="<?php _e('Next Payment', 'finix-wc-subs'); ?>">
                        <?php echo esc_html($subscription->get_date_to_display('next_payment')); ?>
                    </td>
                    <td data-title="<?php _e('Total', 'finix-wc-subs'); ?>">
                        <?php echo wp_kses_post($subscription->get_formatted_order_total()); ?>
                    </td>
                    <td data-title="<?php _e('Custom Description', 'finix-wc-subs'); ?>">
                        <?php
                        // Try subscription meta first (v1.7.1+), fallback to parent order (backward compatibility)
                        $custom_desc = $subscription->get_meta('_finix_custom_description');
                        if (empty($custom_desc) && $subscription->get_parent()) {
                            $custom_desc = $subscription->get_parent()->get_meta('_finix_custom_description');
                        }
                        $subscription_id = $subscription->get_id();
                        $status = $subscription->get_status();
                        $can_edit = in_array($status, array('active', 'on-hold'));
                        ?>
                        <div class="finix-description-container" data-subscription-id="<?php echo esc_attr($subscription_id); ?>">
                            <span class="finix-description-display">
                                <?php echo $custom_desc ? esc_html($custom_desc) : '—'; ?>
                            </span>
                            <?php if ($can_edit): ?>
                            <button type="button" class="finix-edit-description-btn" data-subscription-id="<?php echo esc_attr($subscription_id); ?>">
                                <?php _e('Edit', 'finix-wc-subs'); ?>
                            </button>
                            <div class="finix-description-edit-form" style="display: none;">
                                <input type="text"
                                       class="finix-description-input"
                                       value="<?php echo esc_attr($custom_desc); ?>"
                                       maxlength="50"
                                       placeholder="<?php _e('e.g., Gym Membership, Monthly Software', 'finix-wc-subs'); ?>" />
                                <button type="button" class="finix-save-description-btn">
                                    <?php _e('Save', 'finix-wc-subs'); ?>
                                </button>
                                <button type="button" class="finix-cancel-description-btn">
                                    <?php _e('Cancel', 'finix-wc-subs'); ?>
                                </button>
                                <span class="finix-description-message"></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td data-title="<?php _e('Actions', 'finix-wc-subs'); ?>">
                        <?php self::display_subscription_actions($subscription); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function display_subscription_actions($subscription) {
        $actions = array();
        $subscription_id = $subscription->get_id();
        $status = $subscription->get_status();
        
        $actions['view'] = array(
            'url' => $subscription->get_view_order_url(),
            'name' => __('View', 'finix-wc-subs')
        );

        if ($status === 'active') {
            $actions['suspend'] = array(
                'url' => wp_nonce_url(
                    add_query_arg(array(
                        'finix_action' => 'suspend',
                        'subscription_id' => $subscription_id
                    )),
                    'finix_subscription_action'
                ),
                'name' => __('Suspend', 'finix-wc-subs'),
                'class' => 'suspend'
            );
        } elseif ($status === 'on-hold') {
            $actions['resume'] = array(
                'url' => wp_nonce_url(
                    add_query_arg(array(
                        'finix_action' => 'resume',
                        'subscription_id' => $subscription_id
                    )),
                    'finix_subscription_action'
                ),
                'name' => __('Resume', 'finix-wc-subs'),
                'class' => 'resume'
            );
        }

        if (in_array($status, array('active', 'on-hold'))) {
            $actions['cancel'] = array(
                'url' => wp_nonce_url(
                    add_query_arg(array(
                        'finix_action' => 'cancel',
                        'subscription_id' => $subscription_id
                    )),
                    'finix_subscription_action'
                ),
                'name' => __('Cancel', 'finix-wc-subs'),
                'class' => 'cancel'
            );
        }

        echo '<div class="finix-subscription-actions">';
        foreach ($actions as $key => $action) {
            $class = isset($action['class']) ? ' ' . $action['class'] : '';
            printf(
                '<a href="%s" class="finix-subscription-action%s" onclick="%s">%s</a>',
                esc_url($action['url']),
                esc_attr($class),
                $key === 'cancel' ? 'return confirm(\'' . esc_js(__('Are you sure you want to cancel this subscription?', 'finix-wc-subs')) . '\');' : '',
                esc_html($action['name'])
            );
        }
        echo '</div>';
    }

    public static function handle_subscription_actions() {
        if (!isset($_GET['finix_action']) || !isset($_GET['subscription_id'])) {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'finix_subscription_action')) {
            wc_add_notice(__('Security check failed. Please try again.', 'finix-wc-subs'), 'error');
            return;
        }

        // Check if WooCommerce Subscriptions is active
        if (!function_exists('wcs_get_subscription')) {
            wc_add_notice(__('WooCommerce Subscriptions is not active.', 'finix-wc-subs'), 'error');
            return;
        }

        $action = sanitize_text_field($_GET['finix_action']);
        $subscription_id = absint($_GET['subscription_id']);
        $subscription = wcs_get_subscription($subscription_id);

        if (!$subscription || $subscription->get_user_id() !== get_current_user_id()) {
            wc_add_notice(__('Invalid subscription.', 'finix-wc-subs'), 'error');
            return;
        }

        // Get gateway safely
        if (!function_exists('WC')) {
            wc_add_notice(__('WooCommerce not available.', 'finix-wc-subs'), 'error');
            return;
        }

        $payment_gateways_instance = WC()->payment_gateways();
        if (!$payment_gateways_instance || !method_exists($payment_gateways_instance, 'payment_gateways')) {
            wc_add_notice(__('Payment gateways not available.', 'finix-wc-subs'), 'error');
            return;
        }

        $gateways = $payment_gateways_instance->payment_gateways();

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
            wc_add_notice(__('Finix gateway not found.', 'finix-wc-subs'), 'error');
            return;
        }

        try {
            $api = new Finix_API(
                $gateway->api_key,
                $gateway->api_secret,
                $gateway->merchant_id,
                $gateway->testmode
            );

            $finix_subscription_id = $subscription->get_meta('_finix_subscription_id');

            switch ($action) {
                case 'suspend':
                    $subscription->update_status('on-hold', __('Subscription suspended by customer.', 'finix-wc-subs'));
                    wc_add_notice(__('Subscription suspended successfully.', 'finix-wc-subs'), 'success');
                    break;

                case 'resume':
                    $subscription->update_status('active', __('Subscription resumed by customer.', 'finix-wc-subs'));
                    wc_add_notice(__('Subscription resumed successfully.', 'finix-wc-subs'), 'success');
                    break;

                case 'cancel':
                    if ($finix_subscription_id) {
                        $api->cancel_subscription($finix_subscription_id);
                    }
                    $subscription->update_status('cancelled', __('Subscription cancelled by customer.', 'finix-wc-subs'));
                    wc_add_notice(__('Subscription cancelled successfully.', 'finix-wc-subs'), 'success');
                    break;
            }

            wp_safe_redirect(wc_get_account_endpoint_url('finix-subscriptions'));
            exit;

        } catch (Exception $e) {
            wc_add_notice(__('Error: ', 'finix-wc-subs') . $e->getMessage(), 'error');
        }
    }

    /**
     * Handle AJAX request to update subscription description
     */
    public static function handle_description_update() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'finix_update_description')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'finix-wc-subs')));
        }

        // Check required fields
        if (!isset($_POST['subscription_id']) || !isset($_POST['description'])) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'finix-wc-subs')));
        }

        $subscription_id = absint($_POST['subscription_id']);
        $new_description = sanitize_text_field($_POST['description']);

        // Check if WooCommerce Subscriptions is active
        if (!function_exists('wcs_get_subscription')) {
            wp_send_json_error(array('message' => __('WooCommerce Subscriptions not active.', 'finix-wc-subs')));
        }

        // Get subscription
        $subscription = wcs_get_subscription($subscription_id);

        if (!$subscription) {
            wp_send_json_error(array('message' => __('Subscription not found.', 'finix-wc-subs')));
        }

        // Verify ownership
        if ($subscription->get_user_id() !== get_current_user_id()) {
            wp_send_json_error(array('message' => __('You do not have permission to edit this subscription.', 'finix-wc-subs')));
        }

        // Verify status (only active/on-hold can be edited)
        $status = $subscription->get_status();
        if (!in_array($status, array('active', 'on-hold'))) {
            wp_send_json_error(array('message' => __('Only active or on-hold subscriptions can be edited.', 'finix-wc-subs')));
        }

        // Get Finix subscription ID
        $finix_subscription_id = $subscription->get_meta('_finix_subscription_id');

        if (!$finix_subscription_id) {
            wp_send_json_error(array('message' => __('Finix subscription ID not found.', 'finix-wc-subs')));
        }

        // Get gateway
        if (!function_exists('WC')) {
            wp_send_json_error(array('message' => __('WooCommerce not available.', 'finix-wc-subs')));
        }

        $payment_gateways_instance = WC()->payment_gateways();
        if (!$payment_gateways_instance || !method_exists($payment_gateways_instance, 'payment_gateways')) {
            wp_send_json_error(array('message' => __('Payment gateways not available.', 'finix-wc-subs')));
        }

        $gateways = $payment_gateways_instance->payment_gateways();

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
            wp_send_json_error(array('message' => __('Finix gateway not found.', 'finix-wc-subs')));
        }

        try {
            // Initialize Finix API
            $api = new Finix_API(
                $gateway->api_key,
                $gateway->api_secret,
                $gateway->merchant_id,
                $gateway->testmode
            );

            // Update subscription in Finix
            $api->update_subscription($finix_subscription_id, array(
                'custom_description' => $new_description
            ));

            // Update WooCommerce subscription meta
            $subscription->update_meta_data('_finix_custom_description', $new_description);
            $subscription->save();

            // Add note
            $subscription->add_order_note(sprintf(
                __('Receipt description updated by customer to: "%s"', 'finix-wc-subs'),
                $new_description
            ));

            wp_send_json_success(array(
                'message' => __('Description updated successfully!', 'finix-wc-subs'),
                'description' => $new_description
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error updating description: ', 'finix-wc-subs') . $e->getMessage()));
        }
    }

    public static function enqueue_scripts() {
        if (is_account_page()) {
            wp_enqueue_style(
                'finix-customer-portal',
                FINIX_WC_SUBS_PLUGIN_URL . 'assets/css/customer-portal.css',
                array(),
                FINIX_WC_SUBS_VERSION
            );

            wp_enqueue_script(
                'finix-customer-portal',
                FINIX_WC_SUBS_PLUGIN_URL . 'assets/js/customer-portal.js',
                array('jquery'),
                FINIX_WC_SUBS_VERSION,
                true
            );

            wp_localize_script('finix-customer-portal', 'finixCustomerPortal', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('finix_update_description'),
                'i18n' => array(
                    'confirmCancel' => __('Are you sure you want to cancel this subscription?', 'finix-wc-subs'),
                    'saving' => __('Saving...', 'finix-wc-subs'),
                    'error' => __('An error occurred. Please try again.', 'finix-wc-subs')
                )
            ));
        }
    }
}

Finix_Customer_Portal::init();

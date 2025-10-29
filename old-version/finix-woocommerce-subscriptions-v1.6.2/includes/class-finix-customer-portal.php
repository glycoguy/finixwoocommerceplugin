<?php
/**
 * Finix Customer Portal
 * Allows customers to manage their subscriptions
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
                        $custom_desc = $subscription->get_parent()->get_meta('_finix_custom_description');
                        echo $custom_desc ? esc_html($custom_desc) : 'â€”';
                        ?>
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

        $action = sanitize_text_field($_GET['finix_action']);
        $subscription_id = absint($_GET['subscription_id']);
        $subscription = wcs_get_subscription($subscription_id);

        if (!$subscription || $subscription->get_user_id() !== get_current_user_id()) {
            wc_add_notice(__('Invalid subscription.', 'finix-wc-subs'), 'error');
            return;
        }

        $gateway = WC()->payment_gateways()->payment_gateways()['finix'];
        
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

    public static function enqueue_scripts() {
        if (is_account_page()) {
            wp_enqueue_style(
                'finix-customer-portal',
                FINIX_WC_SUBS_PLUGIN_URL . 'assets/css/customer-portal.css',
                array(),
                FINIX_WC_SUBS_VERSION
            );
        }
    }
}

Finix_Customer_Portal::init();

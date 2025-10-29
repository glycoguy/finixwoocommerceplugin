<?php
/**
 * Finix Tags Helper
 * Manages tags for transactions, subscriptions, and buyer identities
 * 
 * Tags provide crucial tracking and reconciliation capabilities between
 * WooCommerce and Finix systems.
 * 
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Finix_Tags {
    
    /**
     * Tags storage
     * @var array
     */
    private $tags = array();
    
    /**
     * Maximum tag key length (Finix API limit)
     * @var int
     */
    const MAX_KEY_LENGTH = 50;
    
    /**
     * Maximum tag value length (Finix API limit)
     * @var int
     */
    const MAX_VALUE_LENGTH = 500;
    
    /**
     * Add a single tag
     *
     * @param string $key Tag key
     * @param mixed $value Tag value
     * @return bool True on success, false on failure
     */
    public function add($key, $value) {
        // Validate key
        if (empty($key) || !is_string($key)) {
            return false;
        }
        
        // Sanitize and truncate key
        $key = $this->sanitize_key($key);
        if (strlen($key) > self::MAX_KEY_LENGTH) {
            $key = substr($key, 0, self::MAX_KEY_LENGTH);
        }
        
        // Convert value to string and sanitize
        $value = $this->sanitize_value($value);
        if (strlen($value) > self::MAX_VALUE_LENGTH) {
            $value = substr($value, 0, self::MAX_VALUE_LENGTH);
        }
        
        $this->tags[$key] = $value;
        return true;
    }
    
    /**
     * Add multiple tags at once
     *
     * @param array $tags_array Associative array of tags
     * @return int Number of tags successfully added
     */
    public function add_bulk($tags_array) {
        if (!is_array($tags_array)) {
            return 0;
        }
        
        $count = 0;
        foreach ($tags_array as $key => $value) {
            if ($this->add($key, $value)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Remove a tag
     *
     * @param string $key Tag key to remove
     * @return bool True if tag was removed, false if not found
     */
    public function remove($key) {
        if (isset($this->tags[$key])) {
            unset($this->tags[$key]);
            return true;
        }
        return false;
    }
    
    /**
     * Get a specific tag value
     *
     * @param string $key Tag key
     * @param mixed $default Default value if tag not found
     * @return mixed Tag value or default
     */
    public function get($key, $default = null) {
        return isset($this->tags[$key]) ? $this->tags[$key] : $default;
    }
    
    /**
     * Check if a tag exists
     *
     * @param string $key Tag key
     * @return bool True if tag exists
     */
    public function has($key) {
        return isset($this->tags[$key]);
    }
    
    /**
     * Get all tags
     *
     * @return array All tags
     */
    public function get_all() {
        return $this->tags;
    }
    
    /**
     * Clear all tags
     */
    public function clear() {
        $this->tags = array();
    }
    
    /**
     * Prepare tags for API submission
     * Returns object format required by Finix API
     *
     * @return object|null Tags as object, or null if empty
     */
    public function prepare() {
        if (empty($this->tags)) {
            return null;
        }
        
        // Apply filters to allow customization
        $tags = apply_filters('finix_prepare_tags', $this->tags);
        
        return (object) $tags;
    }
    
    /**
     * Sanitize tag key
     * Removes invalid characters and formats for Finix API
     *
     * @param string $key Raw key
     * @return string Sanitized key
     */
    private function sanitize_key($key) {
        // Convert to lowercase
        $key = strtolower($key);
        
        // Replace spaces with underscores
        $key = str_replace(' ', '_', $key);
        
        // Remove any character that's not alphanumeric, underscore, or hyphen
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);
        
        return $key;
    }
    
    /**
     * Sanitize tag value
     * Ensures value is safe for API transmission
     *
     * @param mixed $value Raw value
     * @return string Sanitized value
     */
    private function sanitize_value($value) {
        // Convert to string
        if (is_array($value)) {
            $value = implode(',', $value);
        } elseif (is_object($value)) {
            $value = json_encode($value);
        } else {
            $value = (string) $value;
        }
        
        // Strip tags and encode special characters
        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return $value;
    }
    
    /**
     * Create tags from WooCommerce order
     * Automatically extracts relevant order information
     *
     * @param WC_Order $order WooCommerce order object
     * @return Finix_Tags New tags instance with order data
     */
    public static function from_order($order) {
        $tags = new self();
        
        if (!$order || !is_a($order, 'WC_Order')) {
            return $tags;
        }
        
        // Basic order information
        $tags->add('order_id', $order->get_id());
        $tags->add('order_number', $order->get_order_number());
        $tags->add('order_date', $order->get_date_created()->format('Y-m-d H:i:s'));
        $tags->add('order_status', $order->get_status());
        
        // Customer information
        if ($order->get_customer_id()) {
            $tags->add('customer_id', $order->get_customer_id());
        }
        $tags->add('customer_email', $order->get_billing_email());
        
        // Coupon codes (if any)
        $coupon_codes = $order->get_coupon_codes();
        if (!empty($coupon_codes)) {
            $tags->add('order_coupons', implode(',', $coupon_codes));
        }
        
        // Payment method
        $tags->add('payment_method', $order->get_payment_method());
        
        // Source tracking
        $tags->add('source', 'woocommerce_subscriptions');
        $tags->add('plugin_version', FINIX_WC_SUBS_VERSION);
        
        return $tags;
    }
    
    /**
     * Create tags from WooCommerce subscription
     *
     * @param WC_Subscription $subscription WooCommerce subscription object
     * @return Finix_Tags New tags instance with subscription data
     */
    public static function from_subscription($subscription) {
        $tags = new self();
        
        if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
            return $tags;
        }
        
        // Subscription information
        $tags->add('subscription_id', $subscription->get_id());
        $tags->add('subscription_status', $subscription->get_status());
        $tags->add('billing_period', $subscription->get_billing_period());
        $tags->add('billing_interval', $subscription->get_billing_interval());
        
        // Parent order information
        if ($subscription->get_parent_id()) {
            $tags->add('parent_order_id', $subscription->get_parent_id());
        }
        
        // Customer information
        if ($subscription->get_customer_id()) {
            $tags->add('customer_id', $subscription->get_customer_id());
        }
        
        // Source tracking
        $tags->add('source', 'woocommerce_subscriptions');
        $tags->add('plugin_version', FINIX_WC_SUBS_VERSION);
        
        return $tags;
    }
    
    /**
     * Validate tags before API submission
     * Checks for common issues
     *
     * @return array Array of validation errors, empty if valid
     */
    public function validate() {
        $errors = array();
        
        foreach ($this->tags as $key => $value) {
            // Check key length
            if (strlen($key) > self::MAX_KEY_LENGTH) {
                $errors[] = sprintf('Tag key "%s" exceeds maximum length of %d', $key, self::MAX_KEY_LENGTH);
            }
            
            // Check value length
            if (strlen($value) > self::MAX_VALUE_LENGTH) {
                $errors[] = sprintf('Tag value for "%s" exceeds maximum length of %d', $key, self::MAX_VALUE_LENGTH);
            }
            
            // Check for invalid characters in key
            if (!preg_match('/^[a-z0-9_\-]+$/', $key)) {
                $errors[] = sprintf('Tag key "%s" contains invalid characters', $key);
            }
        }
        
        return $errors;
    }
}

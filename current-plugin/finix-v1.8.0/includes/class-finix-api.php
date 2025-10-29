<?php
/**
 * Finix API Handler
 * Handles all communication with the Finix API
 * 
 * Version: 1.3.4
 * Updated: Fixed country code issue - converts 2-letter ISO to 3-letter ISO codes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Finix_API {
    
    private $api_key;
    private $api_secret;
    private $merchant_id;
    private $sandbox_mode;
    private $api_url;

    public function __construct($api_key, $api_secret, $merchant_id, $sandbox_mode = true) {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        $this->merchant_id = $merchant_id;
        $this->sandbox_mode = $sandbox_mode;
        $this->api_url = $sandbox_mode 
            ? 'https://finix.sandbox-payments-api.com' 
            : 'https://finix.live-payments-api.com';
    }

    /**
     * Convert 2-letter ISO country code to 3-letter ISO code for Finix API
     */
    private function convert_country_code($country_code_2) {
        // Map of 2-letter to 3-letter ISO country codes (ISO 3166-1 alpha-2 to alpha-3)
        $country_map = array(
            'CA' => 'CAN', // Canada
            'US' => 'USA', // United States
            'GB' => 'GBR', // United Kingdom
            'MX' => 'MEX', // Mexico
            'AU' => 'AUS', // Australia
            'FR' => 'FRA', // France
            'DE' => 'DEU', // Germany
            'IT' => 'ITA', // Italy
            'ES' => 'ESP', // Spain
            'NL' => 'NLD', // Netherlands
            'BE' => 'BEL', // Belgium
            'CH' => 'CHE', // Switzerland
            'AT' => 'AUT', // Austria
            'DK' => 'DNK', // Denmark
            'SE' => 'SWE', // Sweden
            'NO' => 'NOR', // Norway
            'FI' => 'FIN', // Finland
            'IE' => 'IRL', // Ireland
            'PL' => 'POL', // Poland
            'PT' => 'PRT', // Portugal
            'GR' => 'GRC', // Greece
            'CZ' => 'CZE', // Czech Republic
            'HU' => 'HUN', // Hungary
            'RO' => 'ROU', // Romania
            'JP' => 'JPN', // Japan
            'KR' => 'KOR', // South Korea
            'CN' => 'CHN', // China
            'IN' => 'IND', // India
            'BR' => 'BRA', // Brazil
            'AR' => 'ARG', // Argentina
            'CL' => 'CHL', // Chile
            'CO' => 'COL', // Colombia
            'PE' => 'PER', // Peru
            'NZ' => 'NZL', // New Zealand
            'SG' => 'SGP', // Singapore
            'MY' => 'MYS', // Malaysia
            'TH' => 'THA', // Thailand
            'ID' => 'IDN', // Indonesia
            'PH' => 'PHL', // Philippines
            'VN' => 'VNM', // Vietnam
            'ZA' => 'ZAF', // South Africa
            'IL' => 'ISR', // Israel
            'AE' => 'ARE', // United Arab Emirates
            'SA' => 'SAU', // Saudi Arabia
            'TR' => 'TUR', // Turkey
            'RU' => 'RUS', // Russia
            'UA' => 'UKR', // Ukraine
        );

        // Convert to uppercase for consistency
        $country_code_2 = strtoupper(trim($country_code_2));
        
        // Return 3-letter code if found, otherwise return original
        if (isset($country_map[$country_code_2])) {
            return $country_map[$country_code_2];
        }
        
        // If already 3 letters, assume it's correct
        if (strlen($country_code_2) === 3) {
            return $country_code_2;
        }
        
        // Log warning if country code not found
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->warning("Unknown country code: {$country_code_2}", array('source' => 'finix-api'));
        }
        
        return $country_code_2;
    }

    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Finix-Version' => '2022-02-01',
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $this->api_secret)
            ),
            'timeout' => 60
        );

        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
            
            // Log request for debugging
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->debug('Finix API Request: ' . $endpoint, array(
                    'source' => 'finix-api',
                    'method' => $method,
                    'data' => $data
                ));
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('Finix API Error: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        $decoded_body = json_decode($body, true);

        // Log response for debugging
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->debug('Finix API Response: ' . $endpoint, array(
                'source' => 'finix-api',
                'status' => $status_code,
                'response' => $decoded_body
            ));
        }

        if ($status_code >= 400) {
            $error_message = isset($decoded_body['_embedded']['errors'][0]['message']) 
                ? $decoded_body['_embedded']['errors'][0]['message'] 
                : 'Unknown error occurred';
            
            // Log detailed error
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->error('Finix API Error: ' . $error_message, array(
                    'source' => 'finix-api',
                    'endpoint' => $endpoint,
                    'status' => $status_code,
                    'full_response' => $decoded_body
                ));
            }
            
            throw new Exception('Finix API Error: ' . $error_message);
        }

        return $decoded_body;
    }

    /**
     * Create Identity (Customer)
     */
    public function create_identity($customer_data) {
        // Convert country code
        $country_code = $this->convert_country_code($customer_data['country']);
        
        $data = array(
            'entity' => array(
                'first_name' => $customer_data['first_name'],
                'last_name' => $customer_data['last_name'],
                'email' => $customer_data['email'],
                'phone' => $customer_data['phone'],
                'personal_address' => array(
                    'line1' => $customer_data['address_line1'],
                    'line2' => $customer_data['address_line2'],
                    'city' => $customer_data['city'],
                    'region' => $customer_data['state'],
                    'postal_code' => $customer_data['postal_code'],
                    'country' => $country_code
                )
            ),
            'tags' => array(
                'customer_type' => 'subscription',
                'woocommerce_user_id' => $customer_data['user_id']
            )
        );

        return $this->make_request('/identities', 'POST', $data);
    }

    /**
     * Create Payment Instrument (Card)
     */
    public function create_payment_instrument($identity_id, $card_data) {
        // Convert country code
        $country_code = $this->convert_country_code($card_data['country']);
        
        $data = array(
            'type' => 'PAYMENT_CARD',
            'identity' => $identity_id,
            'name' => $card_data['name'],
            'number' => $card_data['number'],
            'expiration_month' => $card_data['exp_month'],
            'expiration_year' => $card_data['exp_year'],
            'security_code' => $card_data['cvv'],
            'address' => array(
                'line1' => $card_data['address_line1'],
                'line2' => $card_data['address_line2'],
                'city' => $card_data['city'],
                'region' => $card_data['state'],
                'postal_code' => $card_data['postal_code'],
                'country' => $country_code
            )
        );

        return $this->make_request('/payment_instruments', 'POST', $data);
    }

    /**
     * Create Subscription
     */
    public function create_subscription($subscription_data) {
        $data = array(
            'amount' => $subscription_data['amount'], // Amount in cents
            'currency' => $subscription_data['currency'],
            'linked_to' => $this->merchant_id,
            'linked_type' => 'MERCHANT',
            'nickname' => $subscription_data['nickname'],
            'billing_interval' => $subscription_data['billing_interval'], // DAILY, WEEKLY, MONTHLY, QUARTERLY, ANNUALLY
            'subscription_details' => array(
                'collection_method' => 'BILL_AUTOMATICALLY',
                'send_invoice' => false,
                'send_receipt' => true
            ),
            'buyer_details' => array(
                'identity_id' => $subscription_data['identity_id'],
                'instrument_id' => $subscription_data['instrument_id']
            ),
            'tags' => array(
                'order_id' => $subscription_data['order_id'],
                'subscription_id' => $subscription_data['wc_subscription_id'],
                'custom_description' => isset($subscription_data['custom_description']) ? $subscription_data['custom_description'] : ''
            )
        );

        // Add trial period if specified
        if (isset($subscription_data['trial_days']) && $subscription_data['trial_days'] > 0) {
            $data['subscription_details']['trial_details'] = array(
                'interval_type' => 'DAY',
                'interval_count' => $subscription_data['trial_days']
            );
        }

        // Add start date if specified
        if (isset($subscription_data['start_date'])) {
            $data['start_subscription_at'] = $subscription_data['start_date'];
        }

        return $this->make_request('/subscriptions', 'POST', $data);
    }

    /**
     * Get Subscription
     */
    public function get_subscription($subscription_id) {
        return $this->make_request('/subscriptions/' . $subscription_id, 'GET');
    }

    /**
     * Update Subscription
     */
    public function update_subscription($subscription_id, $update_data) {
        return $this->make_request('/subscriptions/' . $subscription_id, 'PUT', $update_data);
    }

    /**
     * Cancel Subscription
     */
    public function cancel_subscription($subscription_id) {
        return $this->make_request('/subscriptions/' . $subscription_id, 'DELETE');
    }

    /**
     * Create one-time authorization/transfer
     */
    public function create_authorization($authorization_data) {
        $data = array(
            'amount' => $authorization_data['amount'],
            'currency' => $authorization_data['currency'],
            'merchant_identity' => $this->merchant_id,
            'source' => $authorization_data['instrument_id'],
            'tags' => array(
                'order_id' => $authorization_data['order_id']
            )
        );

        return $this->make_request('/authorizations', 'POST', $data);
    }

    /**
     * Capture authorization
     */
    public function capture_authorization($authorization_id, $amount = null) {
        $endpoint = '/authorizations/' . $authorization_id;
        $data = array(
            'capture_amount' => $amount,
            'fee' => 0
        );

        return $this->make_request($endpoint, 'PUT', $data);
    }

    /**
     * Create Transfer (Direct charge)
     */
    public function create_transfer($transfer_data) {
        $data = array(
            'amount' => $transfer_data['amount'],
            'currency' => $transfer_data['currency'],
            'merchant' => $this->merchant_id,
            'source' => $transfer_data['payment_instrument'], // Fixed: was instrument_id
            'tags' => isset($transfer_data['tags']) ? $transfer_data['tags'] : array()
        );

        // Add fraud session ID if provided
        if (!empty($transfer_data['fraud_session_id'])) {
            $data['fraud_session_id'] = $transfer_data['fraud_session_id'];
        }

        return $this->make_request('/transfers', 'POST', $data);
    }

    /**
     * Get Transfer
     */
    public function get_transfer($transfer_id) {
        return $this->make_request('/transfers/' . $transfer_id, 'GET');
    }

    /**
     * Get payment state from transfer result
     *
     * @param array $transfer_result The transfer API response
     * @return string Payment state: 'SUCCEEDED', 'PENDING', 'FAILED', 'CANCELED', or 'UNKNOWN'
     */
    public function get_payment_state($transfer_result) {
        // Check if we have a valid response
        if (empty($transfer_result) || !is_array($transfer_result)) {
            return 'UNKNOWN';
        }

        // Extract state from the response
        // The state could be in different places depending on response structure
        if (isset($transfer_result['response']['state'])) {
            return strtoupper($transfer_result['response']['state']);
        } elseif (isset($transfer_result['state'])) {
            return strtoupper($transfer_result['state']);
        }

        // If no state found, return UNKNOWN
        return 'UNKNOWN';
    }

    /**
     * Refund Transfer
     */
    public function refund_transfer($transfer_id, $amount = null) {
        $data = array(
            'refund_amount' => $amount
        );

        return $this->make_request('/transfers/' . $transfer_id . '/reversals', 'POST', $data);
    }

    /**
     * List transfers for a subscription
     */
    public function list_subscription_transfers($subscription_id) {
        return $this->make_request('/subscriptions/' . $subscription_id . '/transfers', 'GET');
    }

    /**
     * Associate a Finix.js token with an identity
     * This links a client-side tokenized card with a merchant identity
     */
    public function associate_token($token_id, $identity_id) {
        $data = array(
            'identity' => $identity_id
        );

        return $this->make_request('/payment_instruments/' . $token_id, 'PUT', $data);
    }

    /**
     * Create bank account instrument (Canadian EFT)
     */
    public function create_bank_instrument($bank_data) {
        // Convert country code if needed
        $country_code = isset($bank_data['country']) ? $this->convert_country_code($bank_data['country']) : 'CAN';
        
        $data = array(
            'type' => 'BANK_ACCOUNT',
            'identity' => $bank_data['identity_id'],
            'account_type' => $bank_data['account_type'], // checking, savings, business_checking, business_savings
            'account_number' => $bank_data['account_number'],
            'bank_code' => $bank_data['bank_code'], // Institution number (3 digits)
            'branch_code' => $bank_data['branch_code'], // Transit number (5 digits)
            'name' => $bank_data['name'],
            'country' => $country_code // Must be CAN for Canadian accounts
        );

        return $this->make_request('/payment_instruments', 'POST', $data);
    }
}

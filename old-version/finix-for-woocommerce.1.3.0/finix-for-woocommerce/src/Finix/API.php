<?php

namespace FinixWC\Finix;

use stdClass;

/**
 * Main class to interact with Finix API.
 */
class API {
	use ApplePay;
	use Transfer;

	/**
	 * Finix API URLs.
	 * TODO: Move to the Endpoint class.
	 *
	 * @see Endpoint
	 * @see https://docs.finix.com/api#section/Sandbox-and-Live-Endpoints
	 */
	public const SANDBOX_URL = 'https://finix.sandbox-payments-api.com';
	public const LIVE_URL    = 'https://finix.live-payments-api.com';

	/**
	 * Currently use Finix API version.
	 *
	 * @see https://docs.finix.com/additional-resources/developers/authentication-and-api-basics/versioning
	 */
	public const FINIX_API_VERSION = '2022-02-01';

	private $plugin_settings;

	private $sandbox_user;
	private $sandbox_pass;

	private $live_user;
	private $live_pass;

	private $merchant_id;
	private $merchant_id_cad;
	public bool $is_sandbox_mode;
	public $custom_source_tag;

	public Endpoint $endpoint;
	public Tags $tags;

	/**
	 * Constructor for the class.
	 */
	public function __construct() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$this->plugin_settings = get_option( 'woocommerce_finix_gateway_settings', [] );

		$this->sandbox_user      = $this->plugin_settings['test_username'] ?? '';
		$this->sandbox_pass      = $this->plugin_settings['test_password'] ?? '';
		$this->live_user         = $this->plugin_settings['live_username'] ?? '';
		$this->live_pass         = $this->plugin_settings['live_password'] ?? '';
		$this->is_sandbox_mode   = ( $this->plugin_settings['testmode'] ?? 'sandbox' ) === 'sandbox';
		$this->custom_source_tag = $this->plugin_settings['custom_source_tag'] ?? 'woocommerce';
		$this->merchant_id       = $this->is_sandbox_mode
			? ( $this->plugin_settings['test_merchant_id'] ?? '' )
			: ( $this->plugin_settings['live_merchant_id'] ?? '' );
		$this->merchant_id_cad   = $this->is_sandbox_mode
			? ( $this->plugin_settings['test_merchant_id_cad'] ?? '' )
			: ( $this->plugin_settings['live_merchant_id_cad'] ?? '' );

		$this->endpoint = new Endpoint();
	}

	/**
	 * Get the token required for Finix API communication.
	 */
	public function get_token(): string {

		$token = '';

		if ( $this->is_sandbox_mode ) {
			if ( empty( $this->sandbox_user ) || empty( $this->sandbox_pass ) ) {
				return $token;
			}

			$token = $this->sandbox_user . ':' . $this->sandbox_pass;
		} else {
			if ( empty( $this->live_user ) || empty( $this->live_pass ) ) {
				return $token;
			}

			$token = $this->live_user . ':' . $this->live_pass;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $token );
	}

	/**
	 * Create a payment instrument Token.
	 */
	public function create_instrument_token(): InstrumentToken {

		return new InstrumentToken();
	}

	/**
	 * Make a payment.
	 */
	public function make_payment( $amount, $currency, $payment_instrument_token, $fraud_session_id = '', $order_id = '' ): array {

		$data           = new stdClass();
		$data->amount   = $amount;
		$data->currency = $currency;
		$data->source   = $payment_instrument_token;
		$data->merchant = $currency === 'CAD' ? $this->merchant_id_cad : $this->merchant_id;

		if ( ! empty( $fraud_session_id ) ) {
			$data->fraud_session_id = $fraud_session_id;
		}

		$tags = new Tags();

		if ( ! empty( $order_id ) ) {
			$order = wc_get_order( $order_id );

			if ( $order ) {
				$coupons = $order->get_coupon_codes();

				if ( ! empty( $coupons ) ) {
					$tags->add( 'order_coupons', implode( ',', $coupons ) );
				}
			}
		}

		$tags->add_bulk(
			[
				'order_id'   => ! empty( $order_id ) ? $order_id : '',
				'order_date' => gmdate( 'Y-m-d H:i:s' ),
				'source'     => $this->custom_source_tag,
			]
		);

		$tags = apply_filters( 'finixwc_api_make_payment_tags', $tags, $order_id );

		$data->tags = $tags->prepare();

		$token = $this->get_token();

		if ( empty( $token ) ) {
			return [
				'status'   => 401,
				'response' => null,
				'error'    => 'Unauthorized',
			];
		}

		$response = wp_remote_post(
			$this->endpoint::transfers(),
			[
				'headers' => [
					'Accept'        => 'application/hal+json',
					'Content-Type'  => 'application/json',
					'Finix-Version' => self::FINIX_API_VERSION,
					'Authorization' => 'Basic ' . $token,
				],
				'body'    => wp_json_encode( $data ),
				'timeout' => 15,
			]
		);

		$status   = (int) wp_remote_retrieve_response_code( $response );
		$errors   = wp_remote_retrieve_response_message( $response );
		$response = wp_remote_retrieve_body( $response );

		$return = [
			'status'   => $status,
			'response' => json_decode( $response, false ),
		];

		if ( $status !== 201 && $errors ) {
			$return['error'] = $errors;
		}

		return $return;
	}

	/**
	 * Refund a payment.
	 */
	public function refund_payment( string $transaction_id, int $amount, int $order_id, string $refund_reason ): array {

		$data                 = new stdClass();
		$data->amount         = $amount;
		$data->idempotency_id = $order_id . '_' . time();

		$tags = new Tags();

		$tags->add_bulk(
			[
				'order_id'      => $order_id,
				'refund_date'   => gmdate( 'Y-m-d H:i:s' ),
				'source'        => $this->custom_source_tag,
				'refund_reason' => $refund_reason,
			]
		);

		$tags = apply_filters( 'finixwc_api_refund_payment_tags', $tags, $order_id );

		$data->tags = $tags->prepare();

		$token = $this->get_token();

		if ( empty( $token ) ) {
			return [
				'status'   => 401,
				'response' => null,
				'error'    => 'Unauthorized',
			];
		}

		$response = wp_remote_post(
			$this->endpoint::transfer_reversals( $transaction_id ),
			[
				'headers' => [
					'Accept'        => 'application/hal+json',
					'Content-Type'  => 'application/json',
					'Finix-Version' => self::FINIX_API_VERSION,
					'Authorization' => 'Basic ' . $token,
				],
				'body'    => wp_json_encode( $data ),
				'timeout' => 15,
			]
		);

		$status   = (int) wp_remote_retrieve_response_code( $response );
		$errors   = wp_remote_retrieve_response_message( $response );
		$response = wp_remote_retrieve_body( $response );

		$return = [
			'status'   => $status,
			'response' => json_decode( $response ),
		];

		if ( $status !== 200 && $status !== 201 && $errors ) {
			$return['error'] = $errors;
		}

		return $return;
	}

	/**
	 * Retrieve Merchant info.
	 */
	public function get_merchant_info(): array {

		static $merchant_info;

		if ( ! empty( $merchant_info ) ) {
			return $merchant_info;
		}

		$token = $this->get_token();

		if ( empty( $token ) ) {
			$merchant_info = [
				'status'   => 401,
				'response' => null,
				'error'    => 'Unauthorized',
			];

			return $merchant_info;
		}

		$response = wp_remote_get(
			$this->endpoint::merchant( get_woocommerce_currency() === 'CAD' ? $this->merchant_id_cad : $this->merchant_id ),
			[
				'headers' => [
					'Content-Type'  => 'application/json',
					'Finix-Version' => self::FINIX_API_VERSION,
					'Authorization' => 'Basic ' . $token,
				],
				'timeout' => 15,
			]
		);

		$status   = wp_remote_retrieve_response_code( $response );
		$errors   = wp_remote_retrieve_response_message( $response );
		$merchant = wp_remote_retrieve_body( $response );

		$merchant_info = [
			'status'   => $status,
			'response' => json_decode( $merchant ),
		];

		if ( $status !== 200 && $status !== 201 && $errors ) {
			$merchant_info['error'] = $errors;
		}

		return $merchant_info;
	}

	/**
	 * Create a new buyer identity dynamically based on the data available
	 * at the moment of payment.
	 */
	public function new_buyer(): BuyerIdentity {

		return new BuyerIdentity();
	}
}

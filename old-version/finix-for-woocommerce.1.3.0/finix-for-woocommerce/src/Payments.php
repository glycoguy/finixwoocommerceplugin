<?php

namespace FinixWC;

use FinixWC\Gateways\CardGateway;
use FinixWC\Helpers\Convert;
use FinixWC\Helpers\CartData;

/**
 * Payments-specific functionality.
 */
class Payments {

	/**
	 * Only these currencies are supported by Finix.
	 */
	public const SUPPORTED_CURRENCIES = [ 'USD', 'CAD' ];

	/**
	 * Extend WP and WC functionality.
	 */
	public function hooks() {

		add_action( 'woocommerce_thankyou', [ $this, 'on_hold_thankyou_message' ], 5 );

		add_action( 'send_headers', [ $this, 'set_cors_headers' ] );

		add_action( 'wp_ajax_finix_payments_secret', [ $this, 'get_payments_secret' ] );
		add_action( 'wp_ajax_nopriv_finix_payments_secret', [ $this, 'get_payments_secret' ] );

		add_action( 'wp_ajax_validate_shipping_method', [ $this, 'validate_shipping_method' ] );
		add_action( 'wp_ajax_nopriv_validate_shipping_method', [ $this, 'validate_shipping_method' ] );

		add_action( 'wp_ajax_validate_shipping_required', [ $this, 'validate_shipping_required' ] );
		add_action( 'wp_ajax_nopriv_validate_shipping_required', [ $this, 'validate_shipping_required' ] );

		add_action( 'wp_ajax_get_order_tracking_details', [ $this, 'get_order_tracking_details' ] );
		add_action( 'wp_ajax_nopriv_get_order_tracking_details', [ $this, 'get_order_tracking_details' ] );
	}

	/**
	 * CORS header needed for payments.
	 */
	public function set_cors_headers(): void {

		$allowed_origins = [
			get_site_url(),
			'https://js.finix.com',
			'https://applepay.cdn-apple.com',
			'https://apple-pay-gateway-cert.apple.com',
			'https://apple-pay-gateway.apple.com',
			'https://pay.google.com',
		];

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$http_origin = wc_clean( wp_unslash( $_SERVER['HTTP_ORIGIN'] ?? '' ) );

		if ( empty( $http_origin ) ) {
			return;
		}

		if ( in_array( $http_origin, $allowed_origins, true ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			header( 'Access-Control-Allow-Origin: ' . $http_origin );
		} else {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			header( 'Access-Control-Allow-Origin: ' . $http_origin );
			header( 'Access-Control-Allow-Origin: https://applepay.cdn-apple.com' );
			header( 'Access-Control-Allow-Origin: https://js.finix.com' );
			header( 'Access-Control-Allow-Origin: https://pay.google.com' );
		}

		header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE' );
		header( 'Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, Accept-Language, Content-Language, Last-Event-ID, X-HTTP-Method-Override, X-WP-Nonce' );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		// Security headers.
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload' );
		header( 'X-XSS-Protection: 1; mode=block' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: no-referrer-when-downgrade' );
	}

	/**
	 * Notify the customer on the "Thank You" page that their payment is on hold.
	 * This happens with Bank payment.
	 */
	public function on_hold_thankyou_message( int $order_id ): void {

		$order = wc_get_order( $order_id );

		if (
			$order->get_payment_method() === CardGateway::SLUG &&
			$order->get_status() === 'on-hold'
		) {
			wp_add_inline_script(
				'finix-for-woocommerce',
				"document.addEventListener('DOMContentLoaded', function() {
					const feedbackDiv = document.querySelector( '.wc-block-order-confirmation-status' );
					if ( feedbackDiv != null ) {
						let holdParagraph = document.createElement( 'p' );
						holdParagraph.innerHTML = '" . esc_js( __( 'Your order is currently on hold and will be processed once the payment is approved.', 'finix-for-woocommerce' ) ) . "';
						feedbackDiv.appendChild( holdParagraph );
					}
				});"
			);
		}
	}

	/**
	 * Get the secret key for the payments API.
	 */
	public function get_payments_secret(): void {

		// Verify nonce for security.
		if ( empty( $_POST['get_secret_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['get_secret_nonce'] ), 'get_secret_nonce' ) ) {
			wp_send_json_error( __( 'There was an error while processing this request.', 'finix-for-woocommerce' ) );
			die();
		}

		wp_send_json_success( finixwc()->options->get_secret( 'payments' ) );
		die();
	}

	/**
	 * Validate shipping method availability.
	 */
	public function validate_shipping_method(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		if ( WC()->cart->is_empty() ) {
			wp_send_json_success( [ 'valid' => true ], 200 );
			die();
		}

		if ( ! WC()->cart->needs_shipping() ) {
			wp_send_json_success( [ 'valid' => true ], 200 );
			die();
		}

		// Grab request body params.
		$body = file_get_contents( 'php://input' );
		$data = json_decode( $body, true );

		if ( ! wp_verify_nonce( sanitize_key( $data['nonce'] ?? '' ), 'get_secret_action' ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'Please review and fill in all the required information.', 'finix-for-woocommerce' ),
				]
			);
			die();
		}

		$shipping_country   = sanitize_text_field( $data['shipping_country'] ?? '' );
		$shipping_state     = sanitize_text_field( $data['shipping_state'] ?? '' );
		$shipping_postcode  = sanitize_text_field( $data['shipping_postcode'] ?? '' );
		$shipping_city      = sanitize_text_field( $data['shipping_city'] ?? '' );
		$shipping_address_1 = sanitize_text_field( $data['shipping_address_1'] ?? '' );

		$is_block_checkout = ! empty( $data ) && ! empty( $data['is_block_checkout'] );

		if (
			empty( $shipping_country ) ||
			(
				$shipping_country === 'US' &&
				empty( $shipping_state )
			) ||
			empty( $shipping_postcode ) ||
			empty( $shipping_city ) ||
			empty( $shipping_address_1 )
		) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'Please review and fill all the required information.', 'finix-for-woocommerce' ),
				]
			);
			die();
		}

		// If block checkout is enabled, Convert country name to ISO code.
		if ( $is_block_checkout ) {
			$shipping_country = Convert::country_name_to_code( $shipping_country );

			if ( ! $shipping_country ) {
				wp_send_json_error(
					[
						'message' => esc_html__( 'Invalid country provided.', 'finix-for-woocommerce' ),
					]
				);
				die();
			}

			// Convert state name to state code.
			$shipping_state = Convert::us_state_name_to_code( $shipping_country, $shipping_state );

			if ( ! $shipping_state ) {
				wp_send_json_error(
					[
						'message' => esc_html__( 'Invalid state provided.', 'finix-for-woocommerce' ),
					]
				);
				die();
			}
		}

		// Simulate checking shipping methods availability for the provided address.
		$shipping_methods = WC()->shipping()->calculate_shipping_for_package(
			[
				'destination'     => [
					'country'   => $shipping_country,
					'state'     => $shipping_state,
					'postcode'  => $shipping_postcode,
					'city'      => $shipping_city,
					'address'   => $shipping_address_1,
					'address_2' => '',
				],
				'contents'        => WC()->cart->get_cart(),
				'contents_cost'   => WC()->cart->get_cart_contents_total(),
				'applied_coupons' => WC()->cart->get_applied_coupons(),
				'user'            => [
					'ID' => get_current_user_id(),
				],
			]
		);

		if ( empty( $shipping_methods['rates'] ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'No shipping methods available for the provided address.', 'finix-for-woocommerce' ),
				]
			);
			die();
		}

		wp_send_json_success( [ 'valid' => true ], 200 );
		die();
	}

	/**
	 * Check whether the card requires shipping info.
	 */
	public function validate_shipping_required(): void {

		if ( ! WC()->cart->needs_shipping() ) {
			wp_send_json_success( [ 'valid' => true ], 200 );
			die();
		}

		wp_send_json_error( [ 'message' => __( 'Shipping is required for this order.', 'finix-for-woocommerce' ) ] );
		die();
	}

	/**
	 * Returns data for order tracking details based on WC.
	 */
	public function get_order_tracking_details(): void {

		// Prepare the request to get order tracking details.
		$body = file_get_contents( 'php://input' );
		$data = json_decode( $body, true );

		// Sanitize before using data. Skip nonce as it is sanitized in wp_verify_nonce.
		$data = array_map( 'sanitize_text_field', $data );

		$response_data = CartData::prepare_order_tracking_details(
			$data['is_order_pay_page'] ?? false,
			$data['order_id'] ?? 0
		);

		wp_send_json_success( $response_data );
	}
}

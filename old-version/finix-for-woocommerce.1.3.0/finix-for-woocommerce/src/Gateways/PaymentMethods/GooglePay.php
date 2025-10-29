<?php

namespace FinixWC\Gateways\PaymentMethods;

use FinixWC\Gateways\GooglePayGateway;
use FinixWC\Helpers\Assets;
use FinixWC\Helpers\Convert;

/**
 * Pay using Finix via Google Pay.
 */
final class GooglePay extends FinixMethod {

	/**
	 * Payment method name defined by payment methods extending this class.
	 */
	protected $name = GooglePayGateway::SLUG;

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize(): void {

		$this->settings = get_option( 'woocommerce_finix_google_pay_gateway_settings', [] );
		// Get the instance of the gateway from WooCommerce.
		$this->gateway = WC()->payment_gateways()->payment_gateways()[ $this->name ];
	}

	/**
	 * Returns an array of script handles to enqueue for this payment method in the admin context.
	 */
	public function get_payment_method_script_handles_for_admin(): array {

		$handles = [];

		if ( ! has_block( 'woocommerce/checkout' ) ) {
			return $handles;
		}

		return $this->get_common_payment_method_script_handles( $handles );
	}

	/**
	 * Prepare JS scripts handles used to embed JS on the front-end.
	 */
	public function get_payment_method_script_handles(): array { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$handles = [];

		// Only on the checkout page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_checkout() ) {
			return $handles;
		}

		// If settings google_merchant_id is empty, return.
		if ( empty( $this->gateway->google_merchant_id ) ) {
			return $handles;
		}

		// Check if the script is registered, and if not, register it.
		if ( ! wp_script_is( 'finix-sdk', 'registered' ) ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_register_script( 'finix-sdk', Assets::FINIX_SDK_JS_URL, [], null, true );

			$handles[] = 'finix-sdk';
		}

		// Check if the script is registered, and if not, register it.
		if ( ! wp_script_is( 'finix-google-pay', 'registered' ) ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_register_script(
				'finix-google-pay',
				Assets::GOOGLE_SDK_JS_URL,
				[],
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				[
					'strategy'  => 'async',
					'in_footer' => true,
				]
			);

			$handles[] = 'finix-google-pay';
		}

		$handles = $this->get_common_payment_method_script_handles( $handles );

		$merchant_info = finixwc()->finix_api->get_merchant_info();

		if ( empty( $merchant_info['response'] ) || empty( $merchant_info['response']->identity ) ) {
			return $handles;
		}

		$order_amount = '0.00';

		// We might not have a cart over here, so retrieve the data from the current order page.
		if ( is_checkout_pay_page() ) {
			global $wp;

			$order_id = absint( $wp->query_vars['order-pay'] );
			$order    = wc_get_order( $order_id );

			if ( $order ) {
				$order_amount = $order->get_total();
			}
		} else {
			// Get the current cart total as we are most likely on the Checkout page.
			$order_amount = (string) WC()->cart->get_total( '' );
		}

		wp_localize_script(
			'finix_google_pay_gateway-checkout-block',
			'finix_google_pay_params',
			[
				'nonce'              => wp_create_nonce( 'get_secret_action' ),
				'environment'        => $this->gateway->is_sandbox_mode ? 'sandbox' : 'live',
				'merchant_identity'  => esc_html( $merchant_info['response']->identity ),
				'merchant_name'      => esc_html( $this->gateway->merchant_name ),
				'merchant_country'   => sanitize_text_field( wc_format_country_state_string( get_option( 'woocommerce_default_country', '' ) )['country'] ),
				'currency_code'      => sanitize_text_field( get_woocommerce_currency() ),
				'amount'             => Convert::amount_to_number( $order_amount ),
				'gateway'            => esc_html( $this->gateway->id ),
				'billing_data'       => WC()->customer->get_billing(),
				'button_color'       => esc_html( $this->gateway->button_color ),
				'button_type'        => esc_html( $this->gateway->button_type ),
				'button_radius'      => esc_html( $this->gateway->button_radius ),
				'button_locale'      => esc_attr( esc_attr( str_replace( '_', '-', determine_locale() ) ) ),
				'google_merchant_id' => esc_html( $this->gateway->google_merchant_id ),
				'text'               => [
					'error_processing' => esc_html__( 'There was an error while processing your payment. Please try again later.', 'finix-for-woocommerce' ),
					'error_billing'    => esc_html__( 'Please enter a valid billing address.', 'finix-for-woocommerce' ),
				],
				'url'                => [
					'ajax'    => esc_url( admin_url( 'admin-ajax.php' ) ),
					'webhook' => esc_url( WC()->api_request_url( $this->gateway->id ) ),
				],
			]
		);

		// Add CSS for the icon.
		wp_enqueue_style(
			'finix_gateway-google-pay-checkout-block',
			Assets::url( 'css/block-checkout.css' ),
			[],
			Assets::ver()
		);

		wp_set_script_translations( 'finix_google_pay_gateway-checkout-block', 'finix-for-woocommerce' );

		return $handles;
	}

	/**
	 * Shared JS block-based checkout script handles: used in both admin area and front-end.
	 */
	private function get_common_payment_method_script_handles( array $handles ): array {

		wp_register_script(
			'finix_google_pay_gateway-checkout-block',
			Assets::url( 'js/block/build/google-pay.js', false ),
			array_merge(
				( include FINIXWC_PLUGIN_DIR . 'assets/js/block/build/google-pay.asset.php' ) ['dependencies'],
				is_admin() ? [] : [ 'finix-sdk', 'finix-google-pay' ]
			),
			Assets::ver(),
			true
		);

		$handles[] = 'finix_google_pay_gateway-checkout-block';

		return $handles;
	}
}

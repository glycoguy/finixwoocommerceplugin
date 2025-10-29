<?php

namespace FinixWC\Gateways\PaymentMethods;

use FinixWC\Helpers\Assets;

/**
 * Shared code between Card and Bank payment methods.
 */
abstract class CardBankMethod extends FinixMethod {

	/**
	 * When called, invokes any initialization/setup for the integration.
	 */
	public function initialize(): void {

		$this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
	}

	/**
	 * Prepare the payment method data that will be passed to the front-end and used on the Checkout page.
	 */
	public function get_payment_method_data(): array {

		return [
			'title'              => $this->gateway->title,
			'description'        => $this->gateway->description,
			'icon'               => is_checkout() && ! empty( $this->gateway->icon_checkout ) ? $this->gateway->icon_checkout : $this->gateway->icon,
			'nonce'              => wp_create_nonce( 'get_secret_action' ),
			'environment'        => $this->gateway->is_sandbox_mode ? 'sandbox' : 'live',
			'merchant'           => sanitize_text_field( $this->gateway->merchant_id ),
			'merchant_cad'       => sanitize_text_field( $this->gateway->merchant_id_cad ),
			'application'        => sanitize_text_field( $this->gateway->application_id ),
			'finix_form_options' => $this->get_finix_form_params(),
		];
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
	 * Prepare JS scripts handles used to embed JS on the front-end
	 * and also inside the Block Editor.
	 */
	public function get_payment_method_script_handles(): array {

		$handles = [];

		// Only on the checkout page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_checkout() ) {
			return $handles;
		}

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_script( 'finix-sdk', Assets::FINIX_SDK_JS_URL, [], null, true );

		$handles[] = 'finix-sdk';

		return $this->get_common_payment_method_script_handles( $handles );
	}

	/**
	 * Shared block-based checkout script handles: used in both admin area and front-end.
	 */
	protected function get_common_payment_method_script_handles( array $handles ): array {

		// Add CSS for the form.
		wp_enqueue_style(
			'finix_cardbank_gateway-checkout-block',
			Assets::url( 'css/block-checkout.css' ),
			[],
			Assets::ver()
		);

		return $handles;
	}

	/**
	 * Finix form options, shared between card and bank gateways.
	 */
	protected function get_finix_form_params(): array {

		return apply_filters(
			'finixwc_cardbankgateway_finix_form_params',
			[
				'showAddress'       => true,
				'showLabels'        => true,
				'labels'            => [
					'name' => esc_html__( 'Full Name', 'finix-for-woocommerce' ),
				],
				'showPlaceholders'  => true,
				'placeholders'      => [
					'name' => esc_attr__( 'Full Name', 'finix-for-woocommerce' ),
				],
				'hideFields'        => [ 'address_line1', 'address_line2', 'address_city', 'address_state' ],
				'requiredFields'    => [ 'name', 'address_country', 'address_postal_code' ],
				'hideErrorMessages' => false,
				'errorMessages'     => [
					'name' => esc_html__( 'Please enter a valid full name', 'finix-for-woocommerce' ),
				],
			],
			$this->name
		);
	}
}

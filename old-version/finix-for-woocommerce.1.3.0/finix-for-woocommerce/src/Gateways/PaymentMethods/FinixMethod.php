<?php

namespace FinixWC\Gateways\PaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use FinixWC\Gateways\FinixGateway;

/**
 * Main Finix payment method.
 */
abstract class FinixMethod extends AbstractPaymentMethodType {

	/**
	 * Payment gateway powering this method.
	 */
	protected ?FinixGateway $gateway;

	/**
	 * Whether this payment method should be active. If false, the scripts will not be enqueued.
	 */
	public function is_active(): bool {

		static $initialized = [];

		if ( isset( $initialized[ $this->name ] ) && $initialized[ $this->name ] && ! empty( $this->gateway ) ) {
			return $this->gateway->is_available();
		}

		// Get the instance of the gateway from WooCommerce.
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		$this->gateway = $gateways[ $this->name ] ?? null;

		$initialized[ $this->name ] = true;

		return $this->gateway ? $this->gateway->is_available() : false;
	}

	/**
	 * Prepare the payment method data that will be passed to the front-end and used on the Checkout page.
	 * TODO: move here some (if not all) finix_params values. Currently only Card & Bank migrated.
	 */
	public function get_payment_method_data(): array {

		return [
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
			'icon'        => is_checkout() && ! empty( $this->gateway->icon_checkout ) ? $this->gateway->icon_checkout : $this->gateway->icon,
		];
	}

	/**
	 * Prepare JS scripts handles that are used to embed JS in the admin area.
	 */
	public function get_payment_method_script_handles_for_admin(): array {

		// We should load extra assets only when using a block-based checkout.
		if ( has_block( 'woocommerce/checkout' ) ) {
			return $this->get_payment_method_script_handles();
		}

		return [];
	}
}

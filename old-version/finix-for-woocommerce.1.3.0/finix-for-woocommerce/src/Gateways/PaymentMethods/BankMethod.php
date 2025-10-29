<?php

namespace FinixWC\Gateways\PaymentMethods;

use FinixWC\Gateways\BankGateway;
use FinixWC\Helpers\Assets;

/**
 * Pay using Finix via Bank (ACH).
 */
class BankMethod extends CardBankMethod {

	/**
	 * Payment method name defined by payment methods extending this class.
	 */
	protected $name = BankGateway::SLUG;

	/**
	 * Shared JS block-based checkout script handles: used in both admin area and front-end.
	 */
	protected function get_common_payment_method_script_handles( array $handles ): array {

		$handles = parent::get_common_payment_method_script_handles( $handles );

		wp_register_script(
			'finix_bank_gateway-checkout-block',
			Assets::url( 'js/block/build/bank.js', false ),
			array_merge(
				( include FINIXWC_PLUGIN_DIR . 'assets/js/block/build/bank.asset.php' ) ['dependencies'],
				is_admin() ? [] : [ 'finix-sdk' ]
			),
			Assets::ver(),
			true
		);

		$handles[] = 'finix_bank_gateway-checkout-block';

		wp_set_script_translations( 'finix_bank_gateway-checkout-block', 'finix-for-woocommerce' );

		return $handles;
	}
}

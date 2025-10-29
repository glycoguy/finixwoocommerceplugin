<?php

namespace FinixWC\Admin;

use FinixWC\Gateways\ApplePayGateway;
use FinixWC\Gateways\BankGateway;
use FinixWC\Gateways\CardGateway;
use FinixWC\Gateways\GooglePayGateway;
use FinixWC\Payments;

/**
 * Admin-area specific functionality.
 */
class Admin {

	/**
	 * Finix gateways.
	 */
	public const SECTIONS = [
		CardGateway::SLUG,
		BankGateway::SLUG,
		ApplePayGateway::SLUG,
		GooglePayGateway::SLUG,
	];

	/**
	 * Initialize the main logic.
	 */
	public function init(): void {}

	/**
	 * Hooks for the plugin admin area.
	 */
	public function hooks(): void {

		$plugin = plugin_basename( FINIXWC_PLUGIN_FILE );

		add_filter( "plugin_action_links_$plugin", [ $this, 'display_settings_link' ] );
	}

	/**
	 * Display settings link.
	 *
	 * @param array $links Plugin action links.
	 */
	public function display_settings_link( array $links ): array {

		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=finix_gateway&options=1' ) ) . '">' . __( 'Settings', 'finix-for-woocommerce' ) . '</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Determine whether the store operates using a currency that is not supported by Finix.
	 * If so, display a notice.
	 */
	public function maybe_notify_about_unsupported_currency(): void {

		$current_shop_currency = get_woocommerce_currency();

		if ( in_array( $current_shop_currency, Payments::SUPPORTED_CURRENCIES, true ) ) {
			return;
		}
		?>

		<div class="notice notice-error">
			<p>
				<?php
				printf(
					wp_kses( /* translators: %s - current currency code. */
						__( 'Finix for WooCommerce does not currently support the currency of your store: %s.', 'finix-for-woocommerce' ),
						[ 'code' => [] ]
					),
					'<code>' . esc_html( $current_shop_currency ) . '</code>'
				);
				?>
				<br>
				<?php
				printf(
					wp_kses( /* translators: %s - supported currency codes. */
						__( '<strong>All the payments will be declined.</strong> Supported currencies: %s.', 'finix-for-woocommerce' ),
						[
							'strong' => [],
							'code'   => [],
						]
					),
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'<code>' . implode( '</code>, <code>', Payments::SUPPORTED_CURRENCIES ) . '</code>'
				)
				?>
			</p>
		</div>

		<?php
	}
}

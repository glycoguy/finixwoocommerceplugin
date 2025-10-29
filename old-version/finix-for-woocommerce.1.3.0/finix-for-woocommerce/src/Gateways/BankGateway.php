<?php

namespace FinixWC\Gateways;

use FinixWC\Helpers\Assets;

/**
 * Gateway used for Bank (ACH) payments.
 */
final class BankGateway extends CardBankGateway {

	public const SLUG = 'finix_bank_gateway';

	/**
	 * Class constructor for the gateway.
	 */
	public function __construct() {

		$this->id                 = self::SLUG;
		$this->method_title       = __( 'Finix Bank/ACH', 'finix-for-woocommerce' );
		$this->method_description = __( 'Accept direct Bank payments with Finix.', 'finix-for-woocommerce' );

		parent::__construct();

		$this->set_plugin_options();

		$this->icon_checkout = Assets::url( 'images/bank.svg', false );
	}

	/**
	 * Initialize the form fields.
	 * Admin settings for the gateway.
	 */
	public function init_form_fields(): void {

		$this->form_fields = [
			'general_settings' => [
				'title'       => __( 'General Settings', 'finix-for-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'          => [
				'title'       => __( 'Status', 'finix-for-woocommerce' ),
				'label'       => __( 'Enable Bank Gateway', 'finix-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			],
			'title'            => [
				'title'       => __( 'Title', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'finix-for-woocommerce' ),
				'default'     => __( 'Pay with Bank', 'finix-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'description'      => [
				'title'       => __( 'Description', 'finix-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout. It will be wrapped in the paragraph tag.', 'finix-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
		];
	}

	/**
	 * Gateway-specific options page.
	 */
	protected function admin_options_gateway(): void {
		?>

		<div class="links-container">
			<div class="links-content">
				<h2><?php esc_html_e( 'Bank Payment Method', 'finix-for-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'Take bank payments with Finix. You can configure available options on this page.', 'finix-for-woocommerce' ); ?></p>
			</div>
		</div>

		<div class="settings-container">
			<div class="settings-content">
				<table class="form-table">
					<?php $this->generate_settings_html(); ?>
				</table>
			</div>
		</div>

		<div class="clear"></div>

		<?php
	}
}

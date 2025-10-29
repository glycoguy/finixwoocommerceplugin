<?php

namespace FinixWC\Gateways;

use FinixWC\Events\ApplePayPaymentEvent;
use FinixWC\Events\ApplePaySessionEvent;
use FinixWC\Events\ApplePayPaymentCompletedEvent;
use FinixWC\Events\ApplePayRegisterDomain;
use FinixWC\Events\RefundEvent;
use FinixWC\Helpers\Assets;
use FinixWC\Helpers\Convert;
use FinixWC\Helpers\CartData;
use stdClass;

/**
 * Gateway used for Apple Pay payments.
 */
final class ApplePayGateway extends FinixGateway {

	public const SLUG = 'finix_apple_pay_gateway';

	public string $button_style;
	public string $button_type;
	public string $merchant_name;

	/**
	 * Class constructor for woocommerce gateway.
	 */
	public function __construct() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$this->id                 = self::SLUG;
		$this->icon               = Assets::url( 'images/finix-squared.svg', false );
		$this->icon_checkout      = Assets::url( 'images/apple-pay.svg', false );
		$this->method_title       = __( 'Finix Apple Pay', 'finix-for-woocommerce' );
		$this->method_description = __( 'Accept Apple Pay payments with Finix.', 'finix-for-woocommerce' );

		parent::__construct();

		$this->enabled     = $this->get_option( 'enabled' );
		$this->title       = $this->get_option( 'title' ) ?: __( 'Pay with Apple Pay', 'finix-for-woocommerce' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$this->description = $this->get_option( 'description' );

		$this->button_style  = $this->get_option( 'button_style' );
		$this->button_type   = $this->get_option( 'button_type' );
		$this->merchant_name = $this->get_option( 'merchant_name' );

		$this->set_plugin_options();

		$this->hooks();
	}

	/**
	 * Extend WP & WC with Apple Pay specific hooks.
	 */
	public function hooks(): void {

		parent::hooks();

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );

		add_filter( 'woocommerce_order_button_html', [ $this, 'add_finix_apple_pay_button' ] );
		add_filter( 'woocommerce_pay_order_button_html', [ $this, 'add_finix_apple_pay_button' ] );
	}

	/**
	 * Initialize the form fields
	 * Admin settings for the gateway.
	 */
	public function init_form_fields(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_options_page = (bool) absint( $_GET['options'] ?? 0 );

		if ( $is_options_page ) {
			parent::init_form_fields();

			return;
		}

		$this->form_fields = [
			'general_settings' => [
				'title'       => __( 'General Settings', 'finix-for-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'          => [
				'title'       => __( 'Status', 'finix-for-woocommerce' ),
				'label'       => __( 'Enable Apply Pay Gateway', 'finix-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			],
			'title'            => [
				'title'       => __( 'Title', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'finix-for-woocommerce' ),
				'default'     => __( 'Apple Pay', 'finix-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'description'      => [
				'title'       => __( 'Description', 'finix-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout. It will be wrapped in the paragraph tag.', 'finix-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'button_style'     => [
				'title'       => __( 'Button Style', 'finix-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose the style for the Apple Pay button.', 'finix-for-woocommerce' ),
				'options'     => [
					'black'         => __( 'Black', 'finix-for-woocommerce' ),
					'white'         => __( 'White', 'finix-for-woocommerce' ),
					'white-outline' => __( 'White with outline', 'finix-for-woocommerce' ),
				],
				'default'     => 'black',
				'desc_tip'    => true,
			],
			'button_type'      => [
				'title'       => __( 'Button Type', 'finix-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose the type of the Apple Pay button. This affects wording on a button.', 'finix-for-woocommerce' ),
				'options'     => [
					'plain'      => __( 'Plain', 'finix-for-woocommerce' ),
					'continue'   => __( 'Continue', 'finix-for-woocommerce' ),
					'add-money'  => __( 'Add Money', 'finix-for-woocommerce' ),
					'book'       => __( 'Book', 'finix-for-woocommerce' ),
					'buy'        => __( 'Buy', 'finix-for-woocommerce' ),
					'checkout'   => __( 'Check Out', 'finix-for-woocommerce' ),
					'contribute' => __( 'Contribute', 'finix-for-woocommerce' ),
					'donate'     => __( 'Donate', 'finix-for-woocommerce' ),
					'order'      => __( 'Order', 'finix-for-woocommerce' ),
					'pay'        => __( 'Pay', 'finix-for-woocommerce' ),
					'reload'     => __( 'Reload', 'finix-for-woocommerce' ),
					'rent'       => __( 'Rent', 'finix-for-woocommerce' ),
					'set-up'     => __( 'Set Up', 'finix-for-woocommerce' ),
					'subscribe'  => __( 'Subscribe', 'finix-for-woocommerce' ),
					'support'    => __( 'Support', 'finix-for-woocommerce' ),
					'tip'        => __( 'Tip', 'finix-for-woocommerce' ),
					'top-up'     => __( 'Top Up', 'finix-for-woocommerce' ),
				],
				'default'     => 'buy',
				'desc_tip'    => true,
			],
			'merchant_name'    => [
				'title'       => __( 'Display Name', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This will be the merchant name shown to users when making a purchase via Apple Pay.', 'finix-for-woocommerce' ),
				'default'     => get_bloginfo( 'name', 'display' ),
				'desc_tip'    => true,
			],
		];
	}

	/**
	 * Gateway-specific options page.
	 */
	public function admin_options_gateway(): void {
		?>

		<div class="links-container">
			<div class="links-content">
				<h2><?php esc_html_e( 'Apple Pay Payment Method', 'finix-for-woocommerce' ); ?></h2>
				<p>
					<?php esc_html_e( 'Receive payments via Apple Pay with Finix. You can configure the available options on this page.', 'finix-for-woocommerce' ); ?>
					<br>
					<?php
					echo wp_kses( /* translators: %s - plugin version. */
						__( '<strong>Note:</strong> Apple Pay with Finix for WooCommerce is only supported with Live Credentials.', 'finix-for-woocommerce' ),
						[
							'strong' => [],
						]
					);
					?>
				</p>
			</div>
		</div>

		<!-- TODO: check whether the domain is already registered. -->
		<div class="features-container">
			<div class="features-content">
				<h3><?php esc_html_e( 'Register Domain', 'finix-for-woocommerce' ); ?></h3>

				<p>
					<?php esc_html_e( 'This plugin attempts to add the domain association to your server automatically when you click the "Register Domain" button.', 'finix-for-woocommerce' ); ?>
					<strong>
						<?php esc_html_e( 'In order for Apple Pay to display you must test with an iOS device and have a payment method saved in the Apple Wallet.', 'finix-for-woocommerce' ); ?>
					</strong>
				</p>

				<div class="finix-apple-pay register-domain-button">
					<button class="button button-secondary">
						<?php
						printf( /* translators: %s - domain name. */
							esc_html__( 'Register Domain: %s', 'finix-for-woocommerce' ),
							esc_html( wp_parse_url( get_site_url(), PHP_URL_HOST ) )
						);
						?>
					</button>
				</div>

				<div class="finix-apple-pay register-domain-message"></div>
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

	/**
	 * Register admin-area scripts.
	 */
	public function admin_scripts( string $hook_suffix ): void {

		if ( $hook_suffix !== 'woocommerce_page_wc-settings' ) {
			return;
		}

		// Validate we are on gateway settings page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['section'] ) || $_GET['section'] !== $this->id ) {
			return;
		}

		wp_enqueue_script(
			'finix-admin-apple-pay',
			Assets::url( 'js/admin-apple-pay.js' ),
			[ 'jquery' ],
			Assets::ver(),
			true
		);

		wp_localize_script(
			'finix-admin-apple-pay',
			'finix_apple_pay_params',
			[
				'ajax_url' => '/wc-api/' . $this->id,
				'nonce'    => wp_create_nonce( 'get_secret_action' ),
				'text'     => [
					'processing'    => esc_html__( 'Registering domain...', 'finix-for-woocommerce' ),
					'error_billing' => esc_html__( 'Please enter a valid billing address.', 'finix-for-woocommerce' ),
				],
			]
		);
	}

	/**
	 * Render the payment form.
	 */
	public function payment_fields(): void {

		if ( $this->description ) {
			if ( $this->is_sandbox_mode ) {
				$this->description .= ' ' . esc_html__( 'TEST MODE ENABLED. All the payments will go through the sandbox.', 'finix-for-woocommerce' );
				$this->description  = trim( $this->description );
			}

			echo esc_html( trim( $this->description ) );
		}
		?>

		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="finix-wc-form wc-apple-pay-form wc-payment-form">
			<div id="apple-pay-finix-form"></div>

			<input type="hidden" id="finix_apple_pay_success" name="finix_apple_pay_success" value="false">
			<input type="hidden" id="finix_apple_pay_transaction_id" name="finix_apple_pay_transaction_id" value="">
			<?php
			if ( is_checkout_pay_page() ) :
				global $wp;

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended
				$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
				?>
				<input type="hidden" id="finix_apple_pay_order_id" name="finix_apple_pay_order_id" value="<?php echo absint( $wp->query_vars['order-pay'] ?? 0 ); ?>">
				<input type="hidden" id="finix_apple_pay_order_key" name="finix_apple_pay_order_key" value="<?php echo esc_attr( $order_key ); ?>">
			<?php endif; ?>
		</fieldset>

		<?php
	}

	/**
	 * Enqueue the payment script and styles for shortcode-based checkout.
	 */
	public function payment_scripts(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Only if gateway is enabled.
		if ( $this->enabled === 'no' ) {
			return;
		}

		// Only on the checkout page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_checkout() ) {
			return;
		}

		// Only when legacy shortcode is used.
		if ( has_block( 'woocommerce/checkout' ) && ! is_checkout_pay_page() ) {
			return;
		}

		$merchant_info = finixwc()->finix_api->get_merchant_info();

		if ( empty( $merchant_info['response'] ) || empty( $merchant_info['response']->identity ) ) {
			return;
		}

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

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( 'finix-apple-pay', Assets::APPLE_SDK_JS_URL, [], null, true );

		// Include Finix Helpers.
		wp_enqueue_script(
			'finix-helpers',
			Assets::url( 'js/finix-helpers.js' ),
			[ 'jquery' ],
			Assets::ver(),
			true
		);

		wp_enqueue_script(
			'finix_apple_pay_gateway-checkout-shortcode',
			Assets::url( 'js/shortcode-apple-pay.js' ),
			[ 'jquery', 'finix-apple-pay', 'finix-helpers' ],
			Assets::ver(),
			true
		);

		$is_order_pay_page = false;
		$order_id          = 0;

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

		// Get data for order details.
		$order_details = CartData::prepare_order_tracking_details();

		// Pass params to JavaScript and enqueue them.
		wp_localize_script(
			'finix_apple_pay_gateway-checkout-shortcode',
			'finix_apple_pay_params',
			[
				'nonce'             => wp_create_nonce( 'get_secret_action' ),
				'merchant_identity' => esc_html( $merchant_info['response']->identity ),
				'merchant_name'     => esc_html( $this->merchant_name ),
				'merchant_country'  => sanitize_text_field( wc_format_country_state_string( get_option( 'woocommerce_default_country', '' ) )['country'] ),
				'currency_code'     => sanitize_text_field( get_woocommerce_currency() ),
				'amount'            => Convert::amount_to_number( $order_amount ),
				'gateway'           => esc_html( $this->id ),
				'billing_data'      => WC()->customer->get_billing(),
				'products'          => $order_details['products'] ?? new stdClass(),
				'subtotal'          => $order_details['subtotal'],
				'shipping_amount'   => $order_details['shipping_amount'],
				'tax_amount'        => $order_details['tax_amount'],
				'coupons'           => $order_details['coupons'] ?? [],
				'text'              => [
					'error_processing' => esc_html__( 'There was an error while processing your payment. Please try again later.', 'finix-for-woocommerce' ),
					'error_billing'    => esc_html__( 'Please enter a valid billing address.', 'finix-for-woocommerce' ),
					'subtotal'         => esc_html__( 'Subtotal', 'finix-for-woocommerce' ),
					'shipping'         => esc_html__( 'Shipping', 'finix-for-woocommerce' ),
					'tax'              => esc_html__( 'Tax', 'finix-for-woocommerce' ),
					'discount_code'    => esc_html__( 'Discount code:', 'finix-for-woocommerce' ),
				],
				'url'               => [
					'ajax'    => esc_url( admin_url( 'admin-ajax.php' ) ),
					'webhook' => esc_url( WC()->api_request_url( $this->id ) ),
				],
			]
		);

		// Add CSS for the form.
		wp_enqueue_style(
			'finix_gateway-checkout-shortcode',
			Assets::url( 'css/shortcode-checkout.css' ),
			[],
			Assets::ver()
		);
	}

	/**
	 * Display a Finix Apple Pay button on the front-end.
	 *
	 * @param string $place_order_button Default "Place Order" button.
	 */
	public function add_finix_apple_pay_button( string $place_order_button ): string {

		// Validate if the button is already added.
		if ( strpos( $place_order_button, 'finix-apple-pay-button' ) !== false ) {
			return $place_order_button;
		}

		$place_order_button .= '<apple-pay-button id="finix-apple-pay-button" buttonstyle="' . esc_attr( $this->button_style ) . '" type="' . esc_attr( $this->button_type ) . '" locale="' . esc_attr( str_replace( '_', '-', determine_locale() ) ) . '"></apple-pay-button>';

		return $place_order_button;
	}

	/**
	 * Validate payment info on the frontend when the payment is processed.
	 *
	 * @see process_payment()
	 */
	public function validate_fields(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['finix_apple_pay_transaction_id'] ) ) {
			wc_add_notice(
				esc_html__( 'There was an error while processing your payment. Please try again later.', 'finix-for-woocommerce' ),
				'error'
			);

			return false;
		}

		return true;
	}

	/**
	 * Process the payment.
	 */
	public function process_payment( $order_id ): array {

		$order = wc_get_order( $order_id );

		return ( new ApplePayPaymentCompletedEvent( $order ) )
			->set_gateway( $this )
			->process();
	}

	/**
	 * Process order refunds.
	 *
	 * @param int        $order_id Order ID.
	 * @param float|null $amount   Refund amount.
	 * @param string     $reason   Refund reason.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		return ( new RefundEvent( $order_id ) )
			->set_gateway( $this )
			->set_amount( $amount )
			->set_reason( $reason )
			->process();
	}

	/**
	 * Basic structure of webhook processing logic.
	 * All the business logic is located in Events.
	 */
	public function webhook(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		/**
		 * Trigger register API for Apple Pay if isset($_POST['register_domain']) and validate nonce.
		 */
		$should_register_domain = sanitize_key( wp_unslash( $_POST['register_domain'] ?? '' ) );
		$nonce                  = sanitize_key( wp_unslash( $_POST['wp_nonce'] ?? '' ) );

		if (
			$should_register_domain === 'true' &&
			wp_verify_nonce( $nonce, 'get_secret_action' )
		) {
			( new ApplePayRegisterDomain() )->process();
			die;
		}

		try {
			$payload = json_decode( file_get_contents( 'php://input' ), true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'There was an error while processing the payment. Please try again later.', 'finix-for-woocommerce' ),
					'errors'  => wp_json_encode( $e->getMessage() ),
				]
			);
			die();
		}

		if ( empty( $payload ) || ! is_array( $payload ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'There was an error while processing the payment. Please try again later.', 'finix-for-woocommerce' ),
				]
			);
			die();
		}

		/*
		 * Is this a session request?
		 */
		if (
			isset( $payload['session_request'] ) &&
			$payload['session_request'] === true &&
			wp_verify_nonce( $payload['wp_nonce'], 'get_secret_action' )
		) {
			( new ApplePaySessionEvent() )
				->set_session_data( $payload )
				->process();

			die();
		}

		/**
		 * Is this a payment request?
		 */
		if (
			isset( $payload['process_payment'], $payload['billing_info'] ) &&
			$payload['process_payment'] === true &&
			wp_verify_nonce( $payload['wp_nonce'], 'get_secret_action' )
		) {
			( new ApplePayPaymentEvent() )
				->set_gateway( $this )
				->set_billing( $payload['billing_info'] )
				->set_token( $payload['payment_token'] ?? '' )
				->set_merchant( $payload['merchant_identity'] ?? '' )
				->set_order( $payload['order_id'] ?? 0, $payload['order_key'] ?? '' )
				->process();

			die();
		}

		// No valid action found.
		wp_send_json_error(
			[
				'message' => esc_html__( 'There was an error processing the payment.', 'finix-for-woocommerce' ),
			]
		);
		die();
	}
}

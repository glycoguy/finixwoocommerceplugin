<?php

namespace FinixWC\Gateways;

use FinixWC\Events\GooglePayPaymentEvent;
use FinixWC\Events\GooglePayPaymentCompletedEvent;
use FinixWC\Events\RefundEvent;
use FinixWC\Helpers\Assets;
use FinixWC\Helpers\Convert;

/**
 * Gateway used for Google Pay payments.
 */
final class GooglePayGateway extends FinixGateway {

	public const SLUG = 'finix_google_pay_gateway';

	public string $button_type;
	public string $button_color;
	public string $button_radius;
	public string $merchant_name;
	public string $google_merchant_id;

	/**
	 * Class constructor for woocommerce gateway.
	 */
	public function __construct() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$this->id                 = self::SLUG;
		$this->icon               = Assets::url( 'images/finix-squared.svg', false );
		$this->icon_checkout      = Assets::url( 'images/google-pay.svg', false );
		$this->method_title       = __( 'Finix Google Pay', 'finix-for-woocommerce' );
		$this->method_description = __( 'Accept Google Pay payments with Finix.', 'finix-for-woocommerce' );

		parent::__construct();

		$this->enabled     = $this->get_option( 'enabled' );
		$this->title       = $this->get_option( 'title' ) ?: __( 'Pay with Google Pay', 'finix-for-woocommerce' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$this->description = $this->get_option( 'description' );

		$this->button_color  = $this->get_option( 'button_color' );
		$this->button_type   = $this->get_option( 'button_type' );
		$this->button_radius = $this->get_option( 'button_radius' );

		$this->merchant_name      = $this->get_option( 'merchant_name' );
		$this->google_merchant_id = $this->get_option( 'google_merchant_id' );

		$this->set_plugin_options();

		$this->hooks();
	}

	/**
	 * Extend WP & WC with Google Pay-specific hooks.
	 */
	public function hooks(): void {

		parent::hooks();

		add_filter( 'woocommerce_order_button_html', [ $this, 'add_finix_google_pay_button' ] );
		add_filter( 'woocommerce_pay_order_button_html', [ $this, 'add_finix_google_pay_button' ] );
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
			'general_settings'   => [
				'title'       => __( 'General Settings', 'finix-for-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'            => [
				'title'       => __( 'Status', 'finix-for-woocommerce' ),
				'label'       => __( 'Enable Google Pay Gateway', 'finix-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			],
			'title'              => [
				'title'       => __( 'Title', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'finix-for-woocommerce' ),
				'default'     => __( 'Google Pay', 'finix-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'description'        => [
				'title'       => __( 'Description', 'finix-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout. It will be wrapped in the paragraph tag.', 'finix-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'button_color'       => [
				'title'       => __( 'Button Color', 'finix-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose color for the Google Pay button.', 'finix-for-woocommerce' ),
				'options'     => [
					'default' => __( 'Default Button', 'finix-for-woocommerce' ),
					'black'   => __( 'Black Button', 'finix-for-woocommerce' ),
					'white'   => __( 'White Button', 'finix-for-woocommerce' ),
				],
				'default'     => 'default',
				'desc_tip'    => true,
			],
			'button_type'        => [
				'title'       => __( 'Button Type', 'finix-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose which type of Google Pay button you want.', 'finix-for-woocommerce' ),
				'options'     => [
					'plain'     => __( 'Plain Button', 'finix-for-woocommerce' ),
					'buy'       => __( 'Buy Button', 'finix-for-woocommerce' ),
					'checkout'  => __( 'Checkout Button', 'finix-for-woocommerce' ),
					'donate'    => __( 'Donate Button', 'finix-for-woocommerce' ),
					'subscribe' => __( 'Subscribe Button', 'finix-for-woocommerce' ),
					'book'      => __( 'Book Button', 'finix-for-woocommerce' ),
					'order'     => __( 'Order Button', 'finix-for-woocommerce' ),
					'pay'       => __( 'Pay Button', 'finix-for-woocommerce' ),
				],
				'default'     => 'plain',
				'desc_tip'    => true,
			],
			'button_radius'      => [
				'title'       => __( 'Button Corner Radius', 'finix-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Choose the corner radius for the Google Pay button. Default is 5px.', 'finix-for-woocommerce' ),
				'default'     => 5,
				'desc_tip'    => true,
			],
			'merchant_name'      => [
				'title'       => __( 'Display Name', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This will be the merchant name shown to users when making a purchase via Google Pay.', 'finix-for-woocommerce' ),
				'default'     => get_bloginfo( 'name', 'display' ),
				'desc_tip'    => true,
			],
			'google_merchant_id' => [
				'title'       => __( 'Google Merchant ID', 'finix-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This is the Google Merchant ID. You can find it on your Google Business console.', 'finix-for-woocommerce' ),
				'default'     => '',
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
				<h2><?php esc_html_e( 'Google Pay Payment Method', 'finix-for-woocommerce' ); ?></h2>
				<p>
					<?php esc_html_e( 'Receive payments via Google Pay with Finix. You can configure the available options on this page.', 'finix-for-woocommerce' ); ?>
				</p>
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

		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="finix-wc-form wc-google-pay-form wc-payment-form" style="background:transparent;">
			<div id="google-pay-finix-form"></div>

			<input type="hidden" id="finix_google_pay_success" name="finix_google_pay_success" value="false">
			<input type="hidden" id="finix_google_pay_transaction_id" name="finix_google_pay_transaction_id" value="">
			<?php
			if ( is_checkout_pay_page() ) :
				global $wp;

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended
				$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
				?>
				<input type="hidden" id="finix_google_pay_order_id" name="finix_google_pay_order_id" value="<?php echo absint( $wp->query_vars['order-pay'] ?? 0 ); ?>">
				<input type="hidden" id="finix_google_pay_order_key" name="finix_google_pay_order_key" value="<?php echo esc_attr( $order_key ); ?>">
			<?php endif; ?>
		</fieldset>

		<?php
	}

	/**
	 * Enqueue the payment script and styles for shortcode-based checkout.
	 */
	public function payment_scripts(): void {

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

		if ( empty( $this->google_merchant_id ) ) {
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
		wp_enqueue_script(
			'finix-google-pay-sdk',
			Assets::GOOGLE_SDK_JS_URL,
			[],
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			[
				'strategy'  => 'async',
				'in_footer' => true,
			]
		);

		// Include Finix Helpers.
		wp_enqueue_script(
			'finix-helpers',
			Assets::url( 'js/finix-helpers.js' ),
			[ 'jquery' ],
			Assets::ver(),
			true
		);

		// Include finix-google-pay script.
		wp_enqueue_script(
			'finix-google-pay-methods',
			Assets::url( 'js/finix-google-pay.js' ),
			[ 'jquery', 'finix-google-pay-sdk' ],
			Assets::ver(),
			true
		);

		wp_enqueue_script(
			'finix_google_pay_gateway-checkout-shortcode',
			Assets::url( 'js/shortcode-google-pay.js' ),
			[ 'jquery', 'finix-google-pay-sdk', 'finix-google-pay-methods', 'finix-helpers' ],
			Assets::ver(),
			true
		);

		$finix_google_pay_params = [
			'nonce'              => wp_create_nonce( 'get_secret_action' ),
			'environment'        => $this->is_sandbox_mode ? 'sandbox' : 'live',
			'merchant_identity'  => esc_html( $merchant_info['response']->identity ),
			'merchant_name'      => esc_html( $this->merchant_name ),
			'merchant_country'   => sanitize_text_field( wc_format_country_state_string( get_option( 'woocommerce_default_country', '' ) )['country'] ),
			'currency_code'      => sanitize_text_field( get_woocommerce_currency() ),
			'amount'             => Convert::amount_to_number( $order_amount ),
			'gateway'            => esc_html( $this->id ),
			'billing_data'       => WC()->customer->get_billing(),
			'button_color'       => $this->button_color,
			'button_type'        => $this->button_type,
			'button_radius'      => (int) $this->button_radius,
			'button_locale'      => esc_attr( esc_attr( str_replace( '_', '-', determine_locale() ) ) ),
			'google_merchant_id' => esc_html( $this->google_merchant_id ),
			'text'               => [
				'error_processing'      => esc_html__( 'There was an error while processing your payment. Please try again later.', 'finix-for-woocommerce' ),
				'error_setting_payment' => esc_html__( 'There was an error processing the payment through Google Pay. Please try a different method or try again later.', 'finix-for-woocommerce' ),
				'error_billing'         => esc_html__( 'Please enter a valid billing details.', 'finix-for-woocommerce' ),
				'error_shipping'        => esc_html__( 'Please enter a valid shipping details.', 'finix-for-woocommerce' ),
				'error_merchant_id'     => esc_html__( 'Missing details for Google Pay. Please contact the store owner.', 'finix-for-woocommerce' ),
			],
			'url'                => [
				'ajax'    => esc_url( admin_url( 'admin-ajax.php' ) ),
				'webhook' => esc_url( WC()->api_request_url( $this->id ) ),
			],
		];

		// Pass params to JavaScript and enqueue them.

		wp_localize_script(
			'finix-google-pay-methods',
			'finix_google_pay_params',
			$finix_google_pay_params
		);

		wp_localize_script(
			'finix_google_pay_gateway-checkout-shortcode',
			'finix_google_pay_params',
			$finix_google_pay_params
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
	 * Display a Finix Google Pay button on the front-end.
	 *
	 * @param string $place_order_button Default "Place Order" button.
	 */
	public function add_finix_google_pay_button( string $place_order_button ): string {

		// Validate if the button is already added.
		if ( strpos( $place_order_button, 'finix-google-pay-button' ) !== false ) {
			return $place_order_button;
		}

		$place_order_button .= '<div id="finix-google-pay-button" style="text-align: right !important;" locale="' . esc_attr( str_replace( '_', '-', determine_locale() ) ) . '"></div>';

		return $place_order_button;
	}

	/**
	 * Validate payment info on the frontend when the payment is processed.
	 *
	 * @see process_payment()
	 */
	public function validate_fields(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['finix_google_pay_transaction_id'] ) ) {
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

		return ( new GooglePayPaymentCompletedEvent( $order ) )
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

		/**
		 * Is this a payment request?
		 */
		if (
			isset( $payload['process_payment'], $payload['billing_info'] ) &&
			$payload['process_payment'] === true &&
			wp_verify_nonce( $payload['wp_nonce'], 'get_secret_action' )
		) {
			( new GooglePayPaymentEvent() )
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

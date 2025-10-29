<?php

namespace FinixWC\Gateways;

use FinixWC\Events\AchPaymentProcessedEvent;
use FinixWC\Events\AchReturnEvent;
use FinixWC\Events\CardAchPaymentEvent;
use FinixWC\Events\DisputeEvent;
use FinixWC\Events\RefundEvent;
use FinixWC\Helpers\Assets;
use WP_Error;

/**
 * Shared codebase between Bank (ACH) and Card (Credit/Debit) gateways.
 */
abstract class CardBankGateway extends FinixGateway {

	protected bool $is_dispute_managed;
	protected bool $is_ach_return_managed;

	public string $disputes_order_state;
	public string $ach_return_order_state;

	/**
	 * Class constructor for CardBank gateway.
	 */
	public function __construct() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$this->icon = Assets::url( 'images/finix-squared.svg', false );

		parent::__construct();

		$this->enabled     = $this->get_option( 'enabled' );
		$this->title       = $this->get_option( 'title' ) ?: __( 'Pay with Finix', 'finix-for-woocommerce' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$this->description = $this->get_option( 'description' );

		$this->is_dispute_managed   = ( $this->get_option( 'manage_disputes' ) === 'yes' );
		$this->disputes_order_state = $this->get_option( 'disputes_order_state' );

		$this->is_ach_return_managed  = ( $this->get_option( 'manage_ach_returns' ) === 'yes' );
		$this->ach_return_order_state = $this->get_option( 'ach_return_order_state' );

		$this->webhook_password = finixwc()->options->get_secret( 'webhooks' );

		$this->hooks();
	}

	/**
	 * Validate payment info on the frontend when the payment is processed.
	 *
	 * @see process_payment()
	 */
	public function validate_fields(): bool {

		if (
			! isset( $_POST['finix_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['finix_nonce'] ) ), 'get_secret_action' )
		) {
			wc_add_notice(
				esc_html__( 'There was an error while processing your payment. Please try again later.', 'finix-for-woocommerce' ),
				'error'
			);

			return false;
		}

		return true;
	}

	/**
	 * Render the payment form for the Finix JS SDK library.
	 * Loaded only on the shortcode-based checkout page.
	 */
	public function payment_fields(): void {

		// Display some description before the payment form.
		if ( $this->description ) {
			if ( $this->is_sandbox_mode ) {
				$this->description .= ' ' . esc_html__( 'TEST MODE ENABLED. All the payments will go through the sandbox.', 'finix-for-woocommerce' );
			}

			echo esc_html( trim( $this->description ) );
		}
		?>

		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="finix-wc-form wc-payment-form" style="background:transparent;">
			<div id="<?php echo esc_attr( $this->id ); ?>-form"></div>
			<div class="clear"></div>

			<input type="hidden" id="<?php echo esc_attr( $this->id ); ?>_token" name="<?php echo esc_attr( $this->id ); ?>_token" value=""/>

			<div class="clear"></div>
		</fieldset>

		<?php
	}

	/**
	 * Enqueue the payment script and styles for shortcode-based checkout.
	 */
	public function payment_scripts(): void {

		// Only if the gateway is enabled.
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

		if ( wp_script_is( 'finix_gateway-checkout-shortcode', 'enqueued' ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( 'finix-sdk', Assets::FINIX_SDK_JS_URL, [], null, true );

		wp_enqueue_script(
			'finix_gateway-checkout-shortcode',
			Assets::url( 'js/shortcode-card-bank.js' ),
			[ 'jquery', 'finix-sdk' ],
			Assets::ver(),
			true
		);

		// Finix form options.
		$finix_form_options = apply_filters(
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
			$this->id
		);

		// Pass params to JavaScript and enqueue them.
		wp_localize_script(
			'finix_gateway-checkout-shortcode',
			'finix_params',
			[
				'nonce'              => wp_create_nonce( 'get_secret_action' ),
				'environment'        => $this->is_sandbox_mode ? 'sandbox' : 'live',
				'merchant'           => get_woocommerce_currency() === 'CAD' ? esc_html( $this->merchant_id_cad ) : esc_html( $this->merchant_id ),
				'application'        => esc_html( $this->application_id ),
				'text'               => [
					'full_name'      => esc_html__( 'Full Name', 'finix-for-woocommerce' ),
					'error_messages' => [
						'name'         => esc_html__( 'Please enter a valid full name', 'finix-for-woocommerce' ),
						'address_city' => esc_html__( 'Please enter a valid city', 'finix-for-woocommerce' ),
					],
				],
				'finix_form_options' => $finix_form_options,
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
	 * Process order payments.
	 * Validation of whether the payment should proceed is done in the validate_fields() method.
	 *
	 * @see validate_fields()
	 */
	public function process_payment( $order_id ): array { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh,Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$order = wc_get_order( $order_id );

		return ( new CardAchPaymentEvent( $order ) )
			->set_gateway( $this )
			->process();
	}

	/**
	 * Process order refunds.
	 *
	 * @param int        $order_id Order ID.
	 * @param float|null $amount   Refund amount.
	 * @param string     $reason   Refund reason.
	 *
	 * @return bool|WP_Error True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			return new WP_Error( 'error', __( 'Refund failed.', 'finix-for-woocommerce' ) );
		}

		return ( new RefundEvent( $order_id ) )
			->set_gateway( $this )
			->set_amount( $amount )
			->set_reason( $reason )
			->process();
	}

	/**
	 * Used in a webhook() method to validate the incoming request header.
	 */
	protected function is_auth_header_valid( $auth_header ): bool {

		// Extract credentials from the Authorization header.
		$auth_token = explode( ' ', $auth_header );

		if ( count( $auth_token ) !== 2 || $auth_token[0] !== 'Basic' ) {
			return false;
		}

		// Decode and validate user:pass.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded = base64_decode( $auth_token[1], true );

		if ( $decoded === false ) {
			return false;
		}

		// Extract user and pass.
		[ $user, $pass ] = explode( ':', $decoded );

		return ( $user === $this->webhook_user && $pass === $this->webhook_password );
	}

	/**
	 * Basic structure of webhook processing logic.
	 * All the business logic is located in Events.
	 */
	public function webhook(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh,Generic.Metrics.CyclomaticComplexity.MaxExceeded

		// Validate header Finix-Signature exists.
		if ( ! isset( $_SERVER['HTTP_FINIX_SIGNATURE'] ) ) {
			exit;
		}

		// Validate the Authorization Basic token.
		$auth_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ?? '' ) );

		if ( ! $this->is_auth_header_valid( $auth_header ) ) {
			exit;
		}

		try {
			$payload = json_decode( file_get_contents( 'php://input' ), true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			exit;
		}

		if ( empty( $payload ) || ! is_array( $payload ) ) {
			exit;
		}

		// Ignore certain notifications from Finix API.
		if (
			isset( $payload['_embedded']['transfers'][0]['type'] ) &&
			(
				$payload['_embedded']['transfers'][0]['type'] === 'FEE' ||
				$payload['_embedded']['transfers'][0]['type'] === 'ADJUSTMENT'
			)
		) {
			exit;
		}

		/**
		 * Is this an ACH payment confirmation?
		 * It must have a state and order_id in tags.
		 */
		if (
			(
				isset( $payload['entity'] ) &&
				$payload['entity'] === 'transfer'
			) &&
			(
				isset( $payload['type'] ) &&
				$payload['type'] === 'updated'
			) &&
			(
				isset( $payload['_embedded']['transfers'][0]['type'] ) &&
				$payload['_embedded']['transfers'][0]['type'] === 'DEBIT'
			) &&
			! empty( $payload['_embedded']['transfers'][0]['ready_to_settle_at'] ) &&
			isset(
				$payload['_embedded']['transfers'][0]['state'],
				$payload['_embedded']['transfers'][0]['tags']['order_id']
			)
		) {
			$order = wc_get_order( absint( $payload['_embedded']['transfers'][0]['tags']['order_id'] ) );

			if ( ! $order ) {
				exit;
			}

			( new AchPaymentProcessedEvent( $order ) )
				->set_transaction_id( $payload['_embedded']['transfers'][0]['id'] )
				->set_state( $payload['_embedded']['transfers'][0]['state'] )
				->process();

			exit;
		}

		/**
		 * Is this an ACH Return?
		 */
		if (
			$this->is_ach_return_managed &&
			! empty( $payload['id'] ) &&
			(
				isset( $payload['entity'] ) &&
				$payload['entity'] === 'transfer'
			) &&
			(
				isset( $payload['type'] ) &&
				$payload['type'] === 'created'
			) &&
			(
				isset( $payload['_embedded']['transfers'][0]['type'] ) &&
				$payload['_embedded']['transfers'][0]['type'] === 'REVERSAL'
			) &&
			(
				isset( $payload['_embedded']['transfers'][0]['subtype'] ) &&
				$payload['_embedded']['transfers'][0]['subtype'] === 'SYSTEM'
			)
		) {
			$transaction_id = $payload['_embedded']['transfers'][0]['parent_transfer'];

			/*
			 * Unfortunately, Finix webhook event does not return the parent (original)
			 * payment transfer tags where we store the order_id.
			 * So we must use the transaction_id to find the order by its metadata.
			 */
			// phpcs:disable WordPress.DB.SlowDBQuery
			$orders = wc_get_orders(
				[
					'type'       => 'shop_order',
					'meta_key'   => 'finix_transaction_id',
					'meta_value' => sanitize_text_field( $transaction_id ),
				]
			);
			// phpcs:enable WordPress.DB.SlowDBQuery

			if ( empty( $orders ) ) {
				exit;
			}

			( new AchReturnEvent( $orders[0] ) )
				->set_gateway( $this )
				->set_transaction_id( $transaction_id )
				->process();

			exit;
		}

		/*
		 * Is this a dispute?
		 */
		if (
			$this->is_dispute_managed &&
			(
				isset( $payload['entity'] ) &&
				$payload['entity'] === 'dispute'
			) &&
			(
				isset( $payload['type'] ) &&
				( $payload['type'] === 'created' || $payload['type'] === 'updated' )
			) &&
			! empty( $payload['_embedded']['disputes'][0]['transfer'] ) &&
			! empty( $payload['_embedded']['disputes'][0]['tags']['order_id'] ) &&
			! empty( $payload['_embedded']['disputes'][0]['state'] )
		) {
			$order = wc_get_order( $payload['_embedded']['disputes'][0]['tags']['order_id'] );

			if ( ! $order ) {
				exit;
			}

			( new DisputeEvent( $order ) )
				->set_gateway( $this )
				->set_state( $payload['_embedded']['disputes'][0]['state'] )
				->process();

			exit;
		}

		exit;
	}
}

<?php

namespace FinixWC\Events;

use Automattic\WooCommerce\Enums\OrderInternalStatus;
use WC_Order;

/**
 * CardPaymentEvent processes the card and bank payments by CardBankGateway.
 */
class CardAchPaymentEvent extends Event {

	private WC_Order $order;

	/**
	 * Init the event.
	 */
	public function __construct( WC_Order $order ) {

		$this->order = $order;
	}

	/**
	 * Process the payment.
	 */
	public function process(): array { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh,Generic.Metrics.CyclomaticComplexity.MaxExceeded

		if ( empty( $this->order ) ) {
			wc_add_notice(
				esc_html__( 'Payment error: There was an error while processing your payment. Please try again.', 'finix-for-woocommerce' ),
				'error'
			);

			return $this->processing_status();
		}

		$buyer = finixwc()->finix_api->new_buyer()
			->with_order( $this->order )
			->create();

		if ( $buyer['status'] !== 201 || ! is_object( $buyer['response'] ) || empty( $buyer['response']->id ) ) {
			wc_add_notice(
				esc_html__( 'Payment error: There was an error while processing your payment. Please try again.', 'finix-for-woocommerce' ),
				'error'
			);

			$this->order->add_order_note(
				sprintf( /* translators: %s: Error message from Finix. */
					esc_html__( 'Finix Buyer creation failed: %s', 'finix-for-woocommerce' ),
					wp_json_encode( $buyer['response']->_embedded->errors )
				)
			);

			return $this->processing_status();
		}

		$buyer_id = $buyer['response']->id;

		$this->order->add_order_note(
			sprintf( /* translators: %s: Buyer ID from Finix. */
				esc_html__( 'Finix Buyer created successfully: %s', 'finix-for-woocommerce' ),
				esc_html( $buyer_id )
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST[ $this->gateway->id . '_token' ] ) ) {
			wc_add_notice(
				esc_html__( 'Payment error: There was an error while processing your payment. Please try again.', 'finix-for-woocommerce' ),
				'error'
			);

			$this->order->add_order_note(
				esc_html__( 'Finix Payment method creation failed.', 'finix-for-woocommerce' )
			);

			return $this->processing_status();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_instrument_token = sanitize_text_field( wp_unslash( $_POST[ $this->gateway->id . '_token' ] ) );

		$payment_instrument_feedback = finixwc()->finix_api->create_instrument_token()
			->set_token( $payment_instrument_token )
			->set_buyer_identity( $buyer_id )
			->set_buyer_name( $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name() )
			->set_gateway( $this->gateway::SLUG )
			->set_order( $this->order->get_id() )
			->create();

		if (
			$payment_instrument_feedback['status'] !== 201 ||
			! is_object( $payment_instrument_feedback['response'] ) ||
			empty( $payment_instrument_feedback['response']->id )
		) {
			wc_add_notice(
				esc_html__( 'Payment error: There was an error while processing your payment. Please try again.', 'finix-for-woocommerce' ),
				'error'
			);

			$errors = $payment_instrument_feedback['response']->_embedded->errors ?? $payment_instrument_feedback['error'] ?? [ '' ];

			$this->order->add_order_note(
				sprintf( /* translators: %s: Error message from Finix. */
					esc_html__( 'Finix Payment method creation failed: %s', 'finix-for-woocommerce' ),
					wp_json_encode( $errors )
				)
			);

			return $this->processing_status();
		}

		$this->order->add_order_note(
			esc_html__( 'Finix Payment method created successfully', 'finix-for-woocommerce' )
		);

		$method_id = $payment_instrument_feedback['response']->id;
		$amount    = $this->order->get_total() * 100;
		$amount    = (int) (string) $amount;

		$currency           = $this->order->get_currency();
		$payment_instrument = $method_id;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['finix_fraud_session_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$fraud_session_id = sanitize_text_field( wp_unslash( $_POST['finix_fraud_session_id'] ) );
		} else {
			$fraud_session_id = '';
		}

		$response = finixwc()->finix_api->make_payment( $amount, $currency, $payment_instrument, $fraud_session_id, $this->order->get_id() );

		if ( $response['status'] !== 201 ) {
			wc_add_notice(
				esc_html__( 'Payment error: There was an error while processing your payment. Please try again.', 'finix-for-woocommerce' ),
				'error'
			);

			$errors = $response['response']->_embedded->errors ?? '';

			$this->order->add_order_note(
				sprintf( /* translators: %s: Error message from Finix. */
					esc_html__( 'Finix Payment error: %s', 'finix-for-woocommerce' ),
					wp_json_encode( $errors )
				)
			);

			return $this->processing_status();
		}

		$this->set_transaction_id( $response['response']->id );

		// Save custom attribute to order.
		$this->order->update_meta_data( 'finix_transaction_id', $this->transaction_id );
		$this->order->save();

		switch ( $response['response']->state ) {
			// Initial event when paid via ACH.
			case 'PENDING':
			case 'UNKNOWN':
				wc_add_notice(
					esc_html__( 'Payment pending: There was an error while processing your payment. Please try again.', 'finix-for-woocommerce' ),
					'notice'
				);

				$this->order->update_status(
					OrderInternalStatus::ON_HOLD,
					esc_html__( 'Awaiting payment confirmation from Finix.', 'finix-for-woocommerce' )
				);

				return $this->processing_status( 'success', $this->gateway->get_return_url( $this->order ) );

			// Used by card payments.
			case 'SUCCEEDED':
				$this->order->payment_complete( $this->transaction_id );

				$this->order->add_order_note(
					esc_html__( 'Finix Payment processed successfully.', 'finix-for-woocommerce' )
				);

				wc_reduce_stock_levels( $this->order->get_id() );

				WC()->cart->empty_cart();

				return $this->processing_status( 'success', $this->gateway->get_return_url( $this->order ) );

			// Both card and ACH can have this state.
			default:
				wc_add_notice(
					esc_html__( 'Payment failed: There was an error while processing your payment. Please try again.', 'finix-for-woocommerce' ),
					'error'
				);

				$this->order->update_status(
					OrderInternalStatus::FAILED,
					sprintf( /* translators: %s: State of the payment. */
						esc_html__( 'Finix Payment failed. State: %s', 'finix-for-woocommerce' ),
						esc_html( $response['response']->state )
					)
				);

				return $this->processing_status( 'failure', $this->gateway->get_return_url( $this->order ) );
		}
	}

	/**
	 * Return result status.
	 * Default status is error without a redirect.
	 *
	 * @param string $status   Stats of the result.
	 * @param string $redirect URL to redirect.
	 */
	private function processing_status( string $status = 'failure', string $redirect = '' ): array {

		if ( ! in_array( $status, [ 'success', 'error' ], true ) ) {
			$status = 'failure';
		}

		if ( empty( $redirect ) ) {
			$status = '';
		}

		return [
			'result'   => $status,
			'redirect' => $redirect,
		];
	}
}
